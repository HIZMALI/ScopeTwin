/* global Buffer, process */
import { chromium } from '@playwright/test';
import { randomBytes, randomUUID } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

// Real local UI/API only. Setup credentials remain in memory outside capture.
const base = 'http://127.0.0.1:18734';
const out = resolve('artifacts/submission-20261020');
mkdirSync(`${out}/raw`, { recursive: true });
const timeline = JSON.parse(readFileSync(`${out}/timeline.json`, 'utf8'));
const browser = await chromium.launch();
let stage = 'setup';
let token;
let recorded;
const errors = [];
const evidence = { actual_http_evaluations: [], scenes: [], mocked_responses: 0, ai_requests: 0 };
const wait = (ms) => new Promise((done) => setTimeout(done, ms));
const pageProps = async (page) => {
    const version = await page.evaluate(() => JSON.parse(document.querySelector('[data-page]').getAttribute('data-page')).version);
    const response = await page.request.get(page.url(), { headers: { 'X-Inertia': 'true', 'X-Inertia-Version': version, Accept: 'application/json' } });
    if (response.status() !== 200) throw new Error('Actual Inertia props unavailable');
    return (await response.json()).props;
};
async function policy(page, name, effect, reason, op, amount, publish = true, human = false) {
    await page.goto(`${base}/policies/create`);
    await page.getByLabel('Policy name').fill(name);
    if (human) await wait(700);
    await page.getByLabel('Decision effect').selectOption(effect);
    await page.getByLabel('Reason code').fill(reason);
    await page.locator('#value-0').fill('refund.issue');
    await page.getByRole('button', { name: 'Add condition' }).click();
    await page.locator('#field-1').selectOption('amount_minor');
    await page.locator('#op-1').selectOption(op);
    await page.locator('#value-1').fill(String(amount));
    if (human) await wait(1200);
    await page.getByRole('button', { name: 'Add condition' }).click();
    await page.locator('#field-2').selectOption('currency');
    await page.locator('#value-2').fill('USD');
    if (human) await wait(2000);
    await page.getByRole('button', { name: 'Save draft' }).click();
    await page.waitForURL(/policies\/[a-f0-9-]+$/);
    const url = page.url();
    if (publish) {
        await page.getByRole('button', { name: 'Publish saved draft' }).click();
        await page.getByText('A published snapshot is currently live.').waitFor();
    }
    return url;
}
try {
    const setup = await browser.newContext({ baseURL: base, viewport: { width: 1920, height: 1080 } });
    const prep = await setup.newPage();
    await prep.goto('/register');
    await prep.getByLabel('Name', { exact: true }).fill('Synthetic Demo Owner');
    await prep.getByLabel('Email address').fill(`video-${randomUUID()}@example.test`);
    const password = randomBytes(24).toString('hex');
    await prep.getByLabel('Password', { exact: true }).fill(password);
    await prep.getByLabel('Confirm password').fill(password);
    await prep.getByRole('button', { name: 'Create account' }).click();
    await prep.waitForURL(/dashboard$/);
    await prep.goto('/agents');
    await prep.getByLabel('Agent name').fill('Synthetic Refund Agent');
    await prep.getByRole('button', { name: 'Create agent' }).click();
    await prep.getByTestId('issued-token').waitFor();
    token = await prep.getByTestId('issued-token').textContent();
    await prep.getByRole('button', { name: 'I saved the key' }).click();
    const allow = await policy(prep, 'Standard refunds', 'ALLOW', 'REFUND_WITHIN_LIMIT', 'lte', 50000);
    await policy(prep, 'Large refund review', 'REVIEW', 'REFUND_REQUIRES_APPROVAL', 'gt', 50000);
    const state = await setup.storageState();
    await setup.close();

    recorded = await browser.newContext({ baseURL: base, storageState: state, viewport: { width: 1920, height: 1080 }, recordVideo: { dir: `${out}/raw`, size: { width: 1920, height: 1080 } } });
    // Prevent an accidental second paid request; never substitute fake app responses.
    await recorded.route('**/replays/*/advice', async (route) => {
        evidence.ai_requests++;
        await route.abort();
        throw new Error('AI requests are forbidden during recording');
    });
    const page = await recorded.newPage();
    page.on('pageerror', (e) => errors.push(e.name));
    const video = page.video();
    const started = performance.now();
    let decisions = [];
    let report;
    let reportUrl;
    let auditId;
    for (const scene of timeline.scenes) {
        stage = scene.id;
        const scheduled = started + scene.start * 1000;
        if (performance.now() < scheduled) await wait(scheduled - performance.now());
        const actual = (performance.now() - started) / 1000;
        evidence.scenes.push({ id: stage, scheduled: scene.start, actual, drift_ms: Math.round((actual-scene.start)*1000) });
        console.log(`Recording actual scene: ${stage}`);
        if (stage === 'landing') {
            await page.goto('/');
            await page.getByRole('heading', { level: 1 }).waitFor();
            await wait(10000);
            await page.goto('/demo/replay');
            await page.getByText('Synthetic demo data', { exact: true }).waitFor();
        } else if (stage === 'policies') {
            await page.goto('/policies');
            await wait(6500);
            await page.goto(allow);
            await page.getByLabel('Decision effect').waitFor();
        } else if (stage === 'evaluate') {
            await page.goto('/dashboard');
            for (const [action, expected] of [[{ action_name: 'refund.issue', amount_minor: 12000, currency: 'USD' }, 'ALLOW'], [{ action_name: 'refund.issue', amount_minor: 240000, currency: 'USD' }, 'REVIEW'], [{ action_name: 'unknown.action' }, 'DENY']]) {
                const response = await page.request.post('/api/v1/agent/actions/evaluate', { headers: { Authorization: `Bearer ${token}`, 'Idempotency-Key': randomUUID(), Accept: 'application/json' }, data: action });
                if (response.status() !== 200) throw new Error('Evaluation failed');
                const data = await response.json();
                if (data.decision !== expected) throw new Error('Unexpected deterministic decision');
                decisions.push(data);
                evidence.actual_http_evaluations.push({ decision: data.decision, reason_code: data.reason_code });
                await page.reload();
                await wait(1300);
            }
            await page.goto('/decisions');
            await wait(5000);
            await page.goto(`/decisions/${decisions[0].decision_id}`);
            // Read one known synthetic event; no database mutations or private metadata output.
            const input = Buffer.from(JSON.stringify({ id: decisions[0].decision_id })).toString('base64');
            const php = `<?php require '/app/vendor/autoload.php'; $app=require '/app/bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); $p=json_decode(base64_decode('${input}'),true); echo App\\Models\\AuditEvent::where('subject_id',$p['id'])->where('event_type','action.evaluated')->value('id');`;
            auditId = execFileSync('docker', ['exec', '-i', 'scopetwin-build035-app-1', 'php'], { input: php, encoding: 'utf8', windowsHide: true }).trim();
            if (!/^[a-f0-9-]{36}$/.test(auditId)) throw new Error('Missing real audit event');
        } else if (stage === 'draft') {
            await policy(page, 'Refund restriction candidate', 'DENY', 'REFUND_ABOVE_100_BLOCKED', 'gt', 10000, false, true);
            await wait(5000);
            await page.getByRole('button', { name: 'Preview impact', exact: true }).click();
            await page.waitForURL(/replays\/[a-f0-9-]+$/);
            reportUrl = page.url();
            report = (await pageProps(page)).run;
            if (report.summary.changed !== 2 || report.summary.newly_denied !== 2 || report.sample_count !== 3) throw new Error('Replay evidence mismatch');
            evidence.replay = { sample_count: 3, changed: 2, newly_denied: 2 };
        } else if (stage === 'report') {
            await page.goto(reportUrl);
            await wait(10000);
            await page.locator('table.scope-table').evaluate((el) => window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - 70));
        } else if (stage === 'comparison') {
            const props = await pageProps(page);
            const row = props.results.data.find((r) => r.changed && r.input_snapshot.amount_minor === 12000);
            if (!row) throw new Error('Missing changed row');
            await page.goto(`/replays/${report.id}/results/${row.id}`);
            await page.getByText('Original recorded', { exact: true }).waitFor();
        } else if (stage === 'approval') {
            await page.goto('/approvals');
            await page.getByRole('link', { name: 'refund.issue', exact: true }).click();
            await wait(2500);
            await page.getByRole('button', { name: 'Approve', exact: true }).click();
            await wait(2500);
            await page.getByRole('button', { name: 'Cancel', exact: true }).click();
            await wait(1500);
            await page.getByRole('button', { name: 'Approve', exact: true }).click();
            await wait(1500);
            await page.getByRole('button', { name: 'Confirm approval', exact: true }).click();
            await page.getByRole('status').filter({ hasText: 'Approved in ScopeTwin' }).waitFor();
            evidence.approval = 'APPROVED_NO_EXECUTION';
            await wait(4000);
            await page.getByRole('link', { name: 'Open immutable original REVIEW decision' }).click();
        } else if (stage === 'audit') {
            const response = await page.goto(`/audit-events/${auditId}`);
            if (response.status() !== 200) throw new Error('Audit read failed');
            await page.evaluate(() => { document.body.style.fontSize = '24px'; document.body.style.padding = '64px'; });
            evidence.audit = 'ACTUAL_AUTHENTICATED_JSON_ENDPOINT';
        } else if (stage === 'usage') {
            await page.goto('/usage');
            await page.getByRole('heading', { name: 'Usage & Plans' }).waitFor();
            const counts = (await pageProps(page)).counts;
            if (counts.requests !== 3 || counts.approvals !== 1 || counts.replays !== 1) throw new Error('Usage evidence mismatch');
            evidence.usage = counts;
            await wait(10500);
            await page.getByRole('heading', { name: 'Proposed pricing' }).evaluate((el) => el.scrollIntoView());
        } else if (stage === 'close') {
            await page.goto('/');
            await page.getByRole('heading', { level: 1 }).waitFor();
        }
        if ((await page.content()).includes(token) || /st_[a-f0-9]{16}_[a-f0-9]{64}/.test(await page.content())) throw new Error('Credential exposure detected');
        const end = started + (scene.start + scene.seconds) * 1000;
        if (performance.now() > end + 1000) throw new Error('Scene action exceeded synchronized narration');
        if (performance.now() < end) await wait(end - performance.now());
    }
    if (errors.length || evidence.ai_requests) throw new Error('Browser errors');
    await page.screenshot({ path: `${out}/final-frame.png` });
    await recorded.close();
    await video.saveAs(`${out}/raw/ScopeTwin-browser.webm`);
    recorded = null;
    evidence.seconds = timeline.seconds;
    evidence.viewport = { width: 1920, height: 1080 };
    evidence.browser_errors = errors;
    writeFileSync(`${out}/recording-evidence.json`, JSON.stringify(evidence, null, 2));
    console.log('REAL_BROWSER_RECORDING=COMPLETE; AI_REQUESTS=0; CREDENTIALS=NOT_CAPTURED');
} catch (error) {
    console.error(`RECORDING_FAILED_STAGE=${stage}; TYPE=${error.name}; ${error.message.replace(/st_[a-f0-9]{16}_[a-f0-9]{64}/g, '[REDACTED]').slice(0, 300)}`);
    process.exitCode = 1;
} finally {
    if (recorded) await recorded.close();
    await browser.close();
}
