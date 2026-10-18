import { APIRequestContext, expect, Page } from '@playwright/test';
import { randomBytes, randomUUID } from 'node:crypto';

export async function startWorkspace(page: Page) {
    await page.goto('/register');
    await page.getByLabel('Name', { exact: true }).fill('Build 02 Test Owner');
    await page.getByLabel('Email address').fill(`build02-${randomUUID()}@example.test`);
    const password = randomBytes(24).toString('hex');
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByLabel('Confirm password').fill(password);
    await page.getByRole('button', { name: 'Create account' }).click();
    await expect(page).toHaveURL(/dashboard$/);
    await page.goto('/agents');
    await page.getByLabel('Agent name').fill('Replay Test Agent');
    await page.getByRole('button', { name: 'Create agent' }).click();
    const node = page.getByTestId('issued-token');
    await expect(node).toBeVisible();
    const token = (await node.textContent())!;
    await page.getByRole('button', { name: 'I saved the key' }).click();
    return token; // Memory only. No screenshot/trace or output while the key is visible.
}
export async function createPolicy(
    page: Page,
    name: string,
    effect: string,
    reason: string,
    options: { op?: string; amount?: number; publish?: boolean; action?: string } = {},
) {
    await page.goto('/policies/create');
    await page.getByLabel('Policy name').fill(name);
    await page.getByLabel('Decision effect').selectOption(effect);
    await page.getByLabel('Reason code').fill(reason);
    await page.locator('#value-0').fill(options.action ?? 'refund.issue');
    if (options.op) {
        await page.getByRole('button', { name: 'Add condition' }).click();
        await page.locator('#field-1').selectOption('amount_minor');
        await page.locator('#op-1').selectOption(options.op);
        await page.locator('#value-1').fill(String(options.amount ?? 50000));
        await page.getByRole('button', { name: 'Add condition' }).click();
        await page.locator('#field-2').selectOption('currency');
        await page.locator('#value-2').fill('USD');
    }
    await page.getByRole('button', { name: 'Save draft' }).click();
    await expect(page).toHaveURL(/policies\/[a-f0-9-]+$/);
    const url = new URL(page.url()).pathname;
    if (options.publish !== false) {
        await page.getByRole('button', { name: 'Publish saved draft' }).click();
        await expect(page.getByText('A published snapshot is currently live.')).toBeVisible();
    }
    return url;
}
export async function evaluate(request: APIRequestContext, token: string, action: Record<string, unknown>, idempotencyKey = randomUUID()) {
    return request.post('/api/v1/agent/actions/evaluate', {
        headers: { Authorization: `Bearer ${token}`, 'Idempotency-Key': idempotencyKey, Accept: 'application/json' },
        data: action,
    });
}
export async function props(page: Page, url: string) {
    const version = await page.evaluate(() => JSON.parse(document.querySelector('[data-page]')!.getAttribute('data-page')!).version as string);
    const response = await page.request.get(url, { headers: { 'X-Inertia': 'true', 'X-Inertia-Version': version, Accept: 'application/json' } });
    expect(response.status()).toBe(200);
    return (await response.json()).props;
}
export async function noOverflow(page: Page) {
    await expect(page.locator('.scope-main')).toBeVisible();
    const width = await page.evaluate(() => ({ viewport: window.innerWidth, document: document.documentElement.scrollWidth }));
    expect(width.document, `Unexpected overflow at ${new URL(page.url()).pathname}`).toBeLessThanOrEqual(width.viewport);
}
