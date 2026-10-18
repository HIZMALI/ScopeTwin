import { expect, test } from '@playwright/test';
import { createPolicy, evaluate, noOverflow, props, startWorkspace } from './helpers';

test('Advisor disabled fallback, CSRF, explicit mocked UI generation and escaped rendering preserve deterministic facts', async ({
    page,
    request,
}) => {
    const errors: string[] = [];
    const providerRequests: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('request', (req) => {
        if (req.url().includes('backboard.io')) providerRequests.push('unexpected');
    });
    const token = await startWorkspace(page);
    await createPolicy(page, 'Advisor baseline', 'ALLOW', 'ADVISOR_BASELINE');
    expect((await evaluate(request, token, { action_name: 'refund.issue' })).status()).toBe(200);
    await createPolicy(page, 'Advisor candidate', 'DENY', 'ADVISOR_CANDIDATE', { publish: false });
    await page.getByRole('button', { name: 'Preview impact', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'AI Replay Advisor' })).toBeVisible();
    const replayUrl = new URL(page.url()).pathname;
    const before = await props(page, replayUrl);
    await expect(page.getByText('AI explanation is disabled. The deterministic replay remains available.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Explain with AI', exact: true })).toHaveCount(0);
    const csrf = await page.request.post(`${replayUrl}/advice`, {
        headers: { Origin: 'https://untrusted.example', 'Sec-Fetch-Site': 'cross-site', Accept: 'application/json' },
        data: {},
    });
    expect(csrf.status()).toBe(419);
    const missingCsrf = await page.request.post(`${replayUrl}/advice`, { headers: { Accept: 'application/json' }, data: {} });
    expect(missingCsrf.status()).toBe(419);
    const anonymous = await request.post(`${replayUrl}/advice`, { headers: { Accept: 'application/json' }, data: {} });
    expect([401, 419]).toContain(anonymous.status());

    // Explicit browser UI fixture only. The real server remains disabled and never calls a provider.
    await page.route(`**${replayUrl}`, async (route) => {
        const response = await route.fetch();
        if (route.request().headers()['x-inertia'] !== 'true') return route.fulfill({ response });
        const payload = await response.json();
        payload.props.advisor.state = 'ready';
        await route.fulfill({ response, json: payload });
    });
    await page.goto('/replays');
    await page.locator(`a[href="${replayUrl}"]`).first().click();
    const button = page.getByRole('button', { name: 'Explain with AI', exact: true });
    await expect(button).toBeEnabled();
    let calls = 0;
    let release: () => void = () => {};
    const hold = new Promise<void>((resolve) => {
        release = resolve;
    });
    await page.route(`**${replayUrl}/advice`, async (route) => {
        calls++;
        await hold;
        await route.fulfill({
            status: 200,
            json: {
                status: 'completed',
                cached: false,
                cleanup_confirmed: true,
                advice: {
                    explanation: '<b>UI escaping fixture</b>. Human review remains necessary.',
                    questions: ['Which differences need closer review?'],
                },
            },
        });
    });
    expect(calls).toBe(0);
    await button.click();
    await expect(page.getByRole('button', { name: 'Generating explanation…' })).toBeDisabled();
    release();
    await expect(page.getByText('AI-generated explanation (Backboard), not a policy decision')).toBeVisible();
    await expect(page.getByText('<b>UI escaping fixture</b>. Human review remains necessary.', { exact: true })).toBeVisible();
    await expect(page.locator('#advisor-title').locator('..').locator('b')).toHaveCount(0);
    expect(calls).toBe(1);
    await page.unroute(`**${replayUrl}/advice`);
    await page.route(`**${replayUrl}/advice`, (route) => route.fulfill({ status: 429, json: { status: 'quota_exhausted' } }));
    await button.click();
    await expect(page.getByText('The AI call quota has been reached. Try again after the quota window resets.')).toBeVisible();
    expect((await props(page, replayUrl)).run).toEqual(before.run);
    await page.setViewportSize({ width: 390, height: 844 });
    await noOverflow(page);
    await page.screenshot({ path: 'storage/screenshots/build035-advisor-mocked-ui.png', fullPage: true });
    expect(providerRequests).toEqual([]);
    expect(errors).toEqual([]);
});
