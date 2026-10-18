import { expect, test } from '@playwright/test';
import { writeFile } from 'node:fs/promises';
import { createPolicy, evaluate, noOverflow, props, startWorkspace } from './helpers';

test('Replay Lab freezes real HTTP history and compares original, baseline and candidate without publication', async ({ page, request }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const token = await startWorkspace(page);
    await createPolicy(page, 'Standard refunds', 'ALLOW', 'REFUND_WITHIN_LIMIT', { op: 'lte' });
    await createPolicy(page, 'Large refund review', 'REVIEW', 'REFUND_REQUIRES_APPROVAL', { op: 'gt' });
    for (const action of [
        { action_name: 'refund.issue', amount_minor: 12000, currency: 'USD' },
        { action_name: 'refund.issue', amount_minor: 240000, currency: 'USD' },
        { action_name: 'unknown.action' },
    ]) {
        expect((await evaluate(request, token, action)).status()).toBe(200);
    }
    const draftUrl = await createPolicy(page, 'Refund restriction candidate', 'DENY', 'REFUND_ABOVE_100_BLOCKED', {
        op: 'gt',
        amount: 10000,
        publish: false,
    });
    const before = await props(page, '/decisions');
    const approvalsBefore = await props(page, '/approvals');
    const draftPage = await props(page, draftUrl);
    const forged = await page.request.post('/replays', {
        headers: { Origin: 'https://untrusted.example', 'Sec-Fetch-Site': 'cross-site', Accept: 'application/json' },
        data: { draft_id: draftPage.policy.versions[0].id },
    });
    expect(forged.status()).toBe(419);
    await page.getByRole('button', { name: 'Preview impact', exact: true }).click();
    await expect(page).toHaveURL(/replays\/[a-f0-9-]+$/);
    await expect(page.getByRole('heading', { name: 'Draft policy impact' })).toBeVisible();
    const firstUrl = new URL(page.url()).pathname;
    const report = await props(page, firstUrl);
    expect(report.run.summary.changed).toBe(2);
    expect(report.run.summary.newly_denied).toBe(2);
    expect(report.run.sample_count).toBe(3);
    expect((await request.get(firstUrl, { headers: { Accept: 'application/json' } })).status()).toBe(401);
    const outcomes = report.results.data.map(
        (row: {
            input_snapshot: { action_name: string; amount_minor?: number };
            original_effect: string;
            baseline_effect: string;
            candidate_effect: string;
        }) => ({
            action: row.input_snapshot.action_name,
            amount_minor: row.input_snapshot.amount_minor ?? null,
            original: row.original_effect,
            baseline: row.baseline_effect,
            candidate: row.candidate_effect,
        }),
    );
    expect(outcomes).toContainEqual({ action: 'refund.issue', amount_minor: 12000, original: 'ALLOW', baseline: 'ALLOW', candidate: 'DENY' });
    expect(outcomes).toContainEqual({ action: 'refund.issue', amount_minor: 240000, original: 'REVIEW', baseline: 'REVIEW', candidate: 'DENY' });
    expect(outcomes).toContainEqual({ action: 'unknown.action', amount_minor: null, original: 'DENY', baseline: 'DENY', candidate: 'DENY' });
    expect((await props(page, '/decisions')).decisions.data).toEqual(before.decisions.data);
    expect((await props(page, '/approvals')).counts).toEqual(approvalsBefore.counts);
    const policiesAfter = await props(page, '/policies');
    const candidate = policiesAfter.policies.find((p: { name: string }) => p.name === 'Refund restriction candidate');
    expect(candidate.published_version_id).toBeNull();
    expect(candidate.versions[0].state).toBe('DRAFT');
    await writeFile(
        'storage/build02-http-replay.json',
        JSON.stringify({ run_id: report.run.id, changed: report.run.summary.changed, outcomes }, null, 2),
    );
    await page.screenshot({ path: 'storage/screenshots/build02-replay-desktop.png', fullPage: true });
    await page.getByLabel('Impact', { exact: true }).selectOption('1');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page.locator('.scope-table tbody tr')).toHaveCount(2);
    await page.locator('.scope-table-title').first().click();
    await expect(page.getByText('Original recorded', { exact: true })).toBeVisible();
    await expect(page.getByText('Baseline simulated', { exact: true })).toBeVisible();
    await expect(page.getByText('Candidate simulated', { exact: true })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await noOverflow(page);
    await page.goto(firstUrl);
    await noOverflow(page);
    await page.screenshot({ path: 'storage/screenshots/build02-replay-mobile.png', fullPage: true });
    await page.goto('/replays');
    await noOverflow(page);
    await page.getByText('Optional sample filters', { exact: true }).click();
    await page.getByLabel('Action name (exact)').fill('unknown.action');
    await page.getByRole('button', { name: 'Run replay' }).click();
    await expect(page.getByText('No changes detected', { exact: true })).toBeVisible();
    await page.goto('/replays');
    await page.getByText('Optional sample filters', { exact: true }).click();
    await page.getByLabel('Action name (exact)').fill('missing.action');
    await page.getByRole('button', { name: 'Run replay' }).click();
    await expect(page.getByText('No historical requests matched', { exact: true })).toBeVisible();
    await page.goto(draftUrl);
    await page.getByRole('button', { name: 'Publish saved draft' }).click();
    await expect(page.getByText('A published snapshot is currently live.')).toBeVisible();
    await page.getByLabel('Decision effect').selectOption('ALLOW');
    await page.getByLabel('Reason code').fill('RELAXED_CANDIDATE');
    await page.getByRole('button', { name: 'Save draft' }).click();
    await expect(page.getByRole('button', { name: 'Preview impact', exact: true })).toBeEnabled();
    await page.getByRole('button', { name: 'Preview impact', exact: true }).click();
    await expect(page).toHaveURL(/replays\/[a-f0-9-]+$/);
    const changedBaseline = await props(page, new URL(page.url()).pathname);
    expect(changedBaseline.results.data).toEqual(
        expect.arrayContaining([expect.objectContaining({ original_effect: 'ALLOW', baseline_effect: 'DENY', candidate_effect: 'ALLOW' })]),
    );
    expect((await props(page, firstUrl)).run).toEqual(report.run);
    expect((await props(page, firstUrl)).results.data).toEqual(report.results.data);
    await page.goto('/usage');
    await noOverflow(page);
    await expect(page.getByRole('heading', { name: 'Proposed pricing' })).toBeVisible();
    expect((await props(page, '/usage')).counts).toEqual({ requests: 3, approvals: 1, replays: 4 });
    expect(errors).toEqual([]);
});
