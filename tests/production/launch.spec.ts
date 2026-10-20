import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { randomBytes, randomUUID } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createPolicy, evaluate, noOverflow, props } from '../browser/helpers';

test('production public tour: isolation, CSP, desktop/mobile, keyboard and axe', async ({ page, browserName }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    const csp: string[] = [];
    await page.addInitScript(() => document.addEventListener('securitypolicyviolation', e => console.error(`CSP violation: ${e.violatedDirective}`)));
    page.on('console', message => { if (message.text().startsWith('CSP violation:')) csp.push(message.text()); });
    mkdirSync('storage/screenshots', { recursive: true });
    for (const path of ['/', '/demo', '/demo/replay']) {
        const response = await page.goto(path);
        expect(response!.status()).toBe(200);
        expect(response!.headers()['content-security-policy']).toContain("object-src 'none'");
        expect(response!.headers()['set-cookie']).toBeUndefined();
        expect(response!.headers()['strict-transport-security']).toContain('max-age=');
        await expect(page.getByRole('main')).toBeVisible();
        if (path === '/demo/replay') {
            await expect(page.getByText('Synthetic demo data', { exact: true })).toBeVisible();
            await expect(page.getByRole('cell', { name: 'Yes', exact: true })).toHaveCount(2);
            await expect(page.getByText('APPROVED', { exact: true })).toBeVisible();
            expect(await page.content()).not.toMatch(/st_[a-f0-9]{16}_[a-f0-9]{64}|key_hash|judge-fixture@example/);
        }
        if (browserName === 'webkit') {
            test.info().annotations.push({ type: 'limitation', description: 'Windows headless WebKit does not traverse links with Tab/Alt+Tab here. Programmatic skip focus checked; actual Tab traversal is covered by Chromium and Firefox.' });
            await page.getByRole('link', { name: 'Skip to content' }).focus();
        } else {
            await page.keyboard.press('Tab');
        }
        await expect(page.getByRole('link', { name: 'Skip to content' })).toBeFocused();
        await page.keyboard.press('Enter');
        for (const width of [1440, 390]) {
            await page.setViewportSize({ width, height: 1000 });
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
            const accessibility = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
            expect(accessibility.violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.map(n => n.target) }))).toEqual([]);
            if (browserName === 'chromium') await page.screenshot({ path: `storage/screenshots/production-${path === '/' ? 'landing' : 'demo'}-${width}.png`, fullPage: true });
        }
        await page.setViewportSize({ width: 1440, height: 1000 });
    }
    expect((await page.request.get('/demo/018f0000-0000-7000-8000-000000000099')).status()).toBe(404);
    expect((await page.request.post('/demo/replay')).status()).toBe(405);
    expect((await page.request.get('/api/v1/agent/decisions/018f0000-0000-7000-8000-000000000099/approval', { headers: { Accept: 'application/json' } })).status()).toBe(401);
    expect(errors).toEqual([]);
    expect(csp).toEqual([]);
});

test('production HTTP journey: register/login, API, replay, approval, usage and accessible dialog', async ({ page, request, browserName }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    const email = `release-${randomUUID()}@example.test`;
    const password = randomBytes(24).toString('hex');
    await page.goto('/register');
    await page.getByLabel('Name', { exact: true }).fill('Synthetic Release Owner');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByLabel('Confirm password').fill(password);
    await page.getByRole('button', { name: 'Create account' }).click();
    await expect(page).toHaveURL(/dashboard$/);
    await page.getByRole('button', { name: 'Log out' }).first().click();
    await expect(page).toHaveURL(/login$/);
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/dashboard$/);
    await page.goto('/agents');
    await page.getByLabel('Agent name').fill('Synthetic Release Agent');
    await page.getByRole('button', { name: 'Create agent' }).click();
    const node = page.getByTestId('issued-token');
    await expect(node).toBeVisible();
    const token = (await node.textContent())!;
    await page.getByRole('button', { name: 'I saved the key' }).click();
    await expect(node).not.toBeVisible();
    await createPolicy(page, 'Standard refunds', 'ALLOW', 'REFUND_WITHIN_LIMIT', { op: 'lte', amount: 50000 });
    await createPolicy(page, 'Large refund review', 'REVIEW', 'REFUND_REQUIRES_APPROVAL', { op: 'gt', amount: 50000 });
    const small = { action_name: 'refund.issue', amount_minor: 12000, currency: 'USD' };
    const large = { ...small, amount_minor: 240000 };
    const key = randomUUID();
    const allow = await evaluate(request, token, small, key);
    expect(allow.status()).toBe(200);
    const allowed = await allow.json();
    expect(Object.keys(allowed).sort()).toEqual(['approval_required', 'decision', 'decision_id', 'matched_policy_version_id', 'reason_code']);
    expect(allowed.decision).toBe('ALLOW');
    expect(await (await evaluate(request, token, small, key)).json()).toEqual(allowed);
    expect((await evaluate(request, token, large, key)).status()).toBe(409);
    const review = await (await evaluate(request, token, large)).json();
    expect(review.decision).toBe('REVIEW');
    expect((await (await evaluate(request, token, { action_name: 'unknown.action' })).json()).decision).toBe('DENY');
    const draftUrl = await createPolicy(page, 'Proposed stricter refunds', 'DENY', 'PROPOSED_REFUND_LIMIT', { op: 'gt', amount: 10000, publish: false });
    await page.getByRole('button', { name: 'Preview impact' }).click();
    await expect(page).toHaveURL(/replays\/[a-f0-9-]+$/);
    const replayUrl = new URL(page.url()).pathname;
    const replay = await props(page, replayUrl);
    expect(replay.advisor.state).toBe('disabled');
    await expect(page.getByRole('button', { name: 'Explain with AI' })).toHaveCount(0);
    expect(replay.run.summary.changed).toBe(2);
    expect(replay.run.summary.newly_denied).toBe(2);
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const url of ['/dashboard', '/policies', draftUrl, replayUrl, '/approvals', '/usage']) {
            await page.goto(url);
            await noOverflow(page);
            const audit = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
            expect(audit.violations.map(v => ({ id: v.id, nodes: v.nodes.map(n => n.target) })), `${url} at ${width}px`).toEqual([]);
            if (browserName === 'chromium') {
                const capture = url === '/dashboard' ? 'dashboard' : url === draftUrl ? 'policy-editor' : url === replayUrl ? 'replay' : url === '/usage' ? 'usage' : null;
                if (capture) await page.screenshot({ path: `storage/screenshots/production-${capture}-${width}.png`, fullPage: true });
            }
        }
    }
    const usage = await props(page, '/usage');
    expect(usage.counts).toEqual({ requests: 3, approvals: 1, replays: 1 });
    const cookies = await page.context().cookies();
    const session = cookies.find(cookie => cookie.name === 'scopetwin_public_session');
    expect(session).toMatchObject({ secure: true, httpOnly: true, sameSite: 'Lax' });
    await page.goto('/approvals');
    const approvals = await props(page, '/approvals');
    const approvalId = approvals.approvals.data[0].id;
    await page.goto(`/approvals/${approvalId}`);
    await page.getByRole('button', { name: 'Approve', exact: true }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog).toHaveAccessibleName('Confirm approval');
    await expect(dialog.getByRole('button', { name: 'Cancel' })).toBeFocused();
    for (let i = 0; i < 5; i++) {
        await page.keyboard.press('Tab');
        expect(await dialog.evaluate(el => el.contains(document.activeElement))).toBe(true);
    }
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
    await page.getByRole('button', { name: 'Approve', exact: true }).click();
    await dialog.getByRole('button', { name: 'Confirm approval' }).click();
    await expect(page.getByText('Approved in ScopeTwin. No external action was executed.', { exact: true })).toBeVisible();
    const status = await request.get(`/api/v1/agent/decisions/${review.decision_id}/approval`, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } });
    expect((await status.json()).status).toBe('APPROVED');
    expect((await status.json()).execution_performed).toBe(false);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const repeat = await page.request.post(`/approvals/${approvalId}/resolve`, { headers: { 'X-CSRF-TOKEN': csrf!, Accept: 'application/json' }, data: { status: 'REJECTED' } });
    expect(repeat.status()).toBe(409);
    expect(await props(page, replayUrl)).toMatchObject({ run: { summary: { changed: 2, newly_denied: 2 } } });
    const auditHelper = process.env.PRODUCTION_AUDIT_HELPER;
    expect(auditHelper, 'Private operator helper required to locate persisted audit proof').toBeTruthy();
    const proof = JSON.parse(execFileSync('pwsh.exe', ['-NoProfile', '-File', auditHelper!, '-Phase', 'Audit'], {
        encoding: 'utf8', windowsHide: true, env: { ...process.env, RELEASE_DECISION_ID: allowed.decision_id },
    }));
    expect(proof.audit_id).toMatch(/^[a-f0-9-]{36}$/);
    const audit = await page.request.get(`/audit-events/${proof.audit_id}`, { headers: { Accept: 'application/json' } });
    expect(audit.status()).toBe(200);
    expect(await audit.json()).toMatchObject({ event_type: 'action.evaluated', subject_id: allowed.decision_id });
    // A separate production workspace cannot read or modify this workspace's objects.
    const isolated = await page.context().browser()!.newContext({ baseURL: new URL(page.url()).origin });
    const other = await isolated.newPage();
    try {
        await other.goto('/register');
        await other.getByLabel('Name', { exact: true }).fill('Synthetic Isolation Owner');
        await other.getByLabel('Email address').fill(`isolation-${randomUUID()}@example.test`);
        const otherPassword = randomBytes(24).toString('hex');
        await other.getByLabel('Password', { exact: true }).fill(otherPassword);
        await other.getByLabel('Confirm password').fill(otherPassword);
        await other.getByRole('button', { name: 'Create account' }).click();
        await expect(other).toHaveURL(/dashboard$/);
        for (const url of [replayUrl, `/decisions/${allowed.decision_id}`, `/approvals/${approvalId}`, `/audit-events/${proof.audit_id}`]) {
            expect((await other.request.get(url, { headers: { Accept: 'application/json' } })).status()).toBe(404);
        }
        // Registration rotates CSRF; load a fresh document before a manual non-Inertia POST.
        await other.goto('/dashboard');
        const otherCsrf = await other.locator('meta[name="csrf-token"]').getAttribute('content');
        const crossWrite = await other.request.post(`/approvals/${approvalId}/resolve`, {
            headers: { 'X-CSRF-TOKEN': otherCsrf!, Accept: 'application/json' }, data: { status: 'REJECTED' },
        });
        expect(crossWrite.status()).toBe(404);
        expect((await props(other, '/usage')).counts).toEqual({ requests: 0, approvals: 0, replays: 0 });
    } finally { await isolated.close(); }
    const noCsrf = await page.context().browser()!.newContext({ baseURL: new URL(page.url()).origin });
    try {
        const csrfFailure = await noCsrf.request.post('/agents', { headers: { Accept: 'application/json' }, data: { name: 'Must not exist' } });
        expect(csrfFailure.status()).toBe(419);
    } finally { await noCsrf.close(); }
    if (browserName === 'chromium') {
        await page.screenshot({ path: 'storage/screenshots/production-approval-390.png', fullPage: true });
        writeFileSync('storage/production-http-evidence.json', JSON.stringify({ url: new URL(page.url()).origin, environment: 'production', apiEffects: ['ALLOW', 'REVIEW', 'DENY'], changed: 2, newlyDenied: 2, approvalStatus: 'APPROVED', executionPerformed: false, usage: usage.counts, timestamp: new Date().toISOString() }, null, 2));
    }
    expect(errors).toEqual([]);
});
