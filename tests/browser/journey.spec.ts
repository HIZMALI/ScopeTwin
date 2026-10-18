import { expect, test } from '@playwright/test';
import { randomBytes, randomUUID } from 'node:crypto';
import { writeFile } from 'node:fs/promises';

test('register, login, issue key, publish policies, real API decisions, filter, mobile and revoke', async ({ page, request }) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    const email = `browser-${randomUUID()}@example.test`;
    const password = randomBytes(24).toString('hex');
    await page.goto('/register');
    await page.getByLabel('Name', { exact: true }).fill('Demo Owner');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByLabel('Confirm password').fill(password);
    await page.getByRole('button', { name: 'Create account' }).click();
    await expect(page).toHaveURL(/dashboard$/);
    await page.getByRole('button', { name: 'Log out' }).click();
    await expect(page).toHaveURL(/login$/);
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard$/);
    await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Agents' }).click();
    await page.getByLabel('Agent name').fill('Support Agent');
    await page.getByRole('button', { name: 'Create agent' }).click();
    const tokenNode = page.getByTestId('issued-token');
    await expect(tokenNode).toBeVisible();
    const token = (await tokenNode.textContent())!;
    expect(token.startsWith('st_')).toBeTruthy(); // Never log token or include it in screenshots/traces.
    await page.getByRole('button', { name: 'I saved the key' }).click();
    await page.reload();
    await expect(tokenNode).toHaveCount(0);

    async function policy(name: string, effect: string, reason: string, amountOp?: string) {
        await page.goto('/policies/create');
        await page.getByLabel('Policy name').fill(name);
        await page.getByLabel('Decision effect').selectOption(effect);
        await page.getByLabel('Reason code').fill(reason);
        await page.locator('#value-0').fill(amountOp ? 'refund.issue' : 'customer.read');
        if (amountOp) {
            await page.getByRole('button', { name: 'Add condition' }).click();
            await page.locator('#field-1').selectOption('amount_minor');
            await page.locator('#op-1').selectOption(amountOp);
            await page.locator('#value-1').fill('50000');
            await page.getByRole('button', { name: 'Add condition' }).click();
            await page.locator('#field-2').selectOption('currency');
            await page.locator('#value-2').fill('USD');
        }
        await page.getByRole('button', { name: 'Save draft' }).click();
        await expect(page).toHaveURL(/policies\/[a-f0-9-]+$/);
        await page.getByRole('button', { name: 'Publish saved draft' }).click();
        await expect(page.getByRole('button', { name: 'Publish saved draft' })).toBeDisabled();
        await expect(page.getByText('A published snapshot is currently live.')).toBeVisible();
    }
    await policy('Customer lookup', 'ALLOW', 'CUSTOMER_READ_ALLOWED');
    await policy('Standard refunds', 'ALLOW', 'REFUND_WITHIN_LIMIT', 'lte');
    await policy('Large refund review', 'REVIEW', 'REFUND_REQUIRES_APPROVAL', 'gt');

    async function evaluate(data: Record<string, unknown>, key = randomUUID()) {
        return request.post('/api/v1/agent/actions/evaluate', {
            headers: { Authorization: `Bearer ${token}`, 'Idempotency-Key': key, Accept: 'application/json' },
            data,
        });
    }
    const payload = { action_name: 'refund.issue', amount_minor: 12000, currency: 'USD' };
    const key = randomUUID();
    const allow = await evaluate(payload, key);
    expect(allow.status()).toBe(200);
    const saved = await allow.json();
    expect(saved.decision).toBe('ALLOW');
    expect(await (await evaluate(payload, key)).json()).toEqual(saved);
    expect((await evaluate({ ...payload, amount_minor: 10 }, key)).status()).toBe(409);
    const review = await (await evaluate({ ...payload, amount_minor: 240000 })).json();
    const deny = await (await evaluate({ action_name: 'unknown.action' })).json();
    expect(review.decision).toBe('REVIEW');
    expect(deny.reason_code).toBe('DEFAULT_DENY');
    await writeFile('storage/http-demo-results.json', JSON.stringify({ allow: saved, review, deny }, null, 2));
    expect((await (await evaluate({ action_name: 'customer.read' })).json()).decision).toBe('ALLOW');
    expect((await evaluate({ ...payload, amount_minor: -1 })).status()).toBe(422);
    await page.goto('/decisions');
    await expect(page.getByText('4 results', { exact: true })).toBeVisible();
    await page.getByLabel('Decision', { exact: true }).selectOption('REVIEW');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page.getByText('1 results', { exact: true })).toBeVisible();
    await page.locator('tbody a.scope-table-title').first().click();
    await expect(page.getByText('REFUND_REQUIRES_APPROVAL', { exact: true })).toBeVisible();
    await expect(page.getByText('240000', { exact: false })).toBeVisible();
    await page.goto('/dashboard');
    await expect(page.locator('.scope-stat strong').first()).toHaveText('4');
    await page.screenshot({ path: 'storage/screenshots/overview-desktop.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    for (const url of ['/dashboard', '/agents', '/policies', '/decisions']) {
        await page.goto(url);
        await expect(page.locator('.scope-main')).toBeVisible();
        const dimensions = await page.evaluate(() => ({
            width: window.innerWidth,
            scroll: document.documentElement.scrollWidth,
            boxes: [...document.querySelectorAll('.scope-app,.scope-body,.scope-main,.scope-panel,.scope-table-scroll')].map((el) => ({
                cls: el.className,
                width: el.getBoundingClientRect().width,
                overflow: getComputedStyle(el).overflowX,
            })),
            offenders: [...document.querySelectorAll('*')]
                .filter((el) => el.getBoundingClientRect().right > window.innerWidth && getComputedStyle(el).position !== 'absolute')
                .map((el) => ({ tag: el.tagName, cls: el.className, right: el.getBoundingClientRect().right }))
                .slice(0, 15),
        }));
        if (dimensions.scroll > dimensions.width) await page.screenshot({ path: 'storage/screenshots/overflow.png', fullPage: true });
        expect(dimensions.scroll, `${url}: ${JSON.stringify(dimensions)}`).toBeLessThanOrEqual(dimensions.width);
    }
    await page.screenshot({ path: 'storage/screenshots/decisions-mobile.png', fullPage: true });
    await page.goto('/policies');
    await page.getByRole('link', { name: 'Standard refunds', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Policy editor' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
    await page.goto('/agents');
    await page.getByRole('link', { name: 'Support Agent', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'API keys' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
    await page.getByRole('button', { name: 'Revoke key' }).click();
    await expect(page.getByText('REVOKED', { exact: true })).toBeVisible();
    expect((await evaluate(payload)).status()).toBe(401);
    await page.getByRole('button', { name: 'Log out' }).click();
    await expect(page).toHaveURL(/login$/);
    expect(errors).toEqual([]);
});
