/* global process */
import { chromium } from '@playwright/test';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdirSync, openSync, closeSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

// Operator-only local capture. No web auth bypass, credential changes or mock responses.
const live = process.argv.includes('--confirm-one-live-generation');
const prepare = process.argv.includes('--prepare-only');
if (live === prepare) throw new Error('Choose prepare-only or explicit one-generation confirmation');
const base = 'http://127.0.0.1:18734';
const container = 'scopetwin-build035-app-1';
const out = resolve('artifacts/backboard-fix-20261020');
mkdirSync(out, { recursive: true });
function php(code) {
    const raw = execFileSync('docker', ['exec', '-i', container, 'php'], { input: `<?php ${code}`, encoding: 'utf8', windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
    try { return JSON.parse(raw); } catch {
        console.error(JSON.stringify({ local_php_output_valid_json: false, output_bytes: raw.length, warning: /Warning|Deprecated/.test(raw), fatal: /Fatal|Parse error/.test(raw), starts_object: raw.trim().startsWith('{') }));
        throw new Error('Private local PHP helper did not return JSON');
    }
}
const bootstrap = `require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();`;
const fixture = `if(!preg_match('/^scopetwin_[a-z0-9_]*rehearsal$/D',config('database.connections.pgsql.database'))||!config('demo.enabled'))exit(1);$org=App\\Models\\Organization::whereKey(config('demo.organization_id'))->where('slug',config('demo.slug'))->firstOrFail();$run=App\\Models\\ReplayRun::forOrganization($org->id)->firstOrFail();`;
const fingerprint = `$hashes=[];foreach(['agents','agent_api_keys','policies','policy_versions','action_requests','policy_decisions','audit_events','approval_requests','replay_runs','replay_results']as$table){$hashes[$table]=hash('sha256',Illuminate\\Support\\Facades\\DB::table($table)->where('organization_id',$org->id)->orderBy('id')->get()->toJson());}$fingerprint=hash('sha256',json_encode($hashes));`;
let browser;
let context;
let attempted = 0;
const evidence = { generation_request_limit: 1, mocked_responses: 0, deterministic_unchanged: false };
let stage = 'session';
try {
    // Make a normal signed Laravel session in memory for the dedicated synthetic owner.
    // Neither the random existing password nor APP_KEY is returned or changed.
    const session = php(`${bootstrap}${fixture}${fingerprint}
    $user=App\\Models\\User::findOrFail($run->created_by_user_id);$s=app('session')->driver();$s->start();$guard=Illuminate\\Support\\Facades\\Auth::guard('web');$guard->login($user);$s->save();$name=config('session.cookie');$prefix=Illuminate\\Cookie\\CookieValuePrefix::create($name,app('encrypter')->getKey());echo json_encode(['name'=>$name,'value'=>app('encrypter')->encrypt($prefix.$s->getId(),false),'path'=>'/replays/'.$run->id,'fingerprint'=>$fingerprint,'state'=>app(App\\Services\\ReplayAdvisor::class)->state()]);`);
    if (live && session.state !== 'ready') throw new Error('Provider is not enabled and ready');
    stage = 'browser';
    browser = await chromium.launch();
    context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, ...(live ? { recordVideo: { dir: `${out}/raw`, size: { width: 1920, height: 1080 } } } : {}) });
    await context.addCookies([{ name: session.name, value: session.value, url: base, httpOnly: true, sameSite: 'Lax' }]);
    const page = await context.newPage();
    stage = 'authenticated_report';
    await page.goto(base + session.path);
    await page.getByRole('heading', { name: 'AI Replay Advisor', exact: true }).waitFor();
    await page.locator('#advisor-title').evaluate((element) => element.scrollIntoView({ block: 'start' }));
    if (prepare) {
        console.log('AUTHENTICATED_SYNTHETIC_UI=VERIFIED; GENERATION_REQUESTS=0');
    } else {
        await context.route('**/replays/*/advice', async (route) => {
            attempted++;
            if (attempted > 1) return route.abort('blockedbyclient');
            return route.continue();
        });
        const button = page.getByRole('button', { name: 'Explain with AI', exact: true });
        await button.waitFor();
        // Durable one-shot guard. A failure still consumes this operator attempt.
        closeSync(openSync(`${out}/live-ui-attempt.lock`, 'wx'));
        const started = new Date().toISOString();
        const responsePromise = page.waitForResponse((r) => r.url().endsWith('/advice') && r.request().method() === 'POST', { timeout: 45000 });
        await button.click();
        const response = await responsePromise;
        const data = await response.json();
        evidence.http_status = response.status();
        evidence.status = typeof data.status === 'string' ? data.status : 'invalid_response';
        evidence.cached = data.cached === true;
        evidence.cleanup_confirmed = data.cleanup_confirmed === true;
        evidence.advice_present = typeof data.advice?.explanation === 'string' && data.advice.explanation.trim().length > 0;
        await page.waitForTimeout(1000);
        // Docker may put PHP output on stderr; inspect both in memory, print neither.
        const logs = spawnSync('docker', ['logs', '--since', started, container], { encoding: 'utf8', windowsHide: true });
        const raw = (logs.stdout ?? '') + (logs.stderr ?? '');
        const match = raw.split('\n').map((line) => { try { return JSON.parse(line.match(/\{.*\}/)?.[0] ?? 'null'); } catch { return null; } }).filter((d) => d?.event === 'replay_advisor').at(-1);
        if (match) evidence.diagnostics = Object.fromEntries(Object.entries(match).filter(([key]) => ['advisor_status','failure_stage','validation_reason','http_status','cleanup_status','cleanup_http_status','elapsed_ms','generation_attempted','response_status'].includes(key)));
        const after = php(`${bootstrap}${fixture}${fingerprint}echo json_encode(['fingerprint'=>$fingerprint]);`);
        evidence.deterministic_unchanged = after.fingerprint === session.fingerprint;
        if (data.status === 'completed') await page.getByText('AI-generated explanation (Backboard), not a policy decision', { exact: true }).waitFor();
        await page.screenshot({ path: `${out}/advisor-live.png` });
        await page.waitForTimeout(26000);
        const video = page.video();
        await context.close(); context = null;
        await video.saveAs(`${out}/advisor-live-browser.webm`);
        evidence.generation_requests = attempted;
        evidence.real_ui_visible = data.status === 'completed';
        writeFileSync(`${out}/live-ui-evidence.json`, JSON.stringify(evidence, null, 2));
        if (response.status() !== 200 || data.status !== 'completed' || data.cached || !evidence.cleanup_confirmed || !evidence.deterministic_unchanged || attempted !== 1) throw new Error('Live acceptance criteria failed; no retry permitted');
        console.log('LIVE_UI=ACCEPTED; CLEANUP=CONFIRMED; DETERMINISTIC=UNCHANGED; GENERATION_REQUESTS=1');
    }
} catch (error) {
    evidence.generation_requests = attempted;
    writeFileSync(`${out}/live-ui-evidence.json`, JSON.stringify(evidence, null, 2));
    console.error(`LIVE_ADVISOR_CHECK=FAILED; STAGE=${stage}; ERROR_TYPE=${error.name}; NO_RETRY; PRIVATE_DETAILS_WITHHELD`);
    process.exitCode = 1;
} finally {
    if (context) await context.close();
    if (browser) await browser.close();
}
