import { chromium, expect } from '@playwright/test';
import { createHash } from 'node:crypto';
import { mkdir, writeFile } from 'node:fs/promises';
import process from 'node:process';

if (!process.argv.includes('--confirm-idle-window')) throw new Error('Explicit 16-minute idle measurement confirmation required.');
const origin = 'https://scopetwin-hizmali.onrender.com';
const output = 'artifacts/public-release-20261021';
await mkdir(output, { recursive: true });
const hash = value => createHash('sha256').update(value).digest('hex');

async function readDemo(capture) {
    const browser = await chromium.launch();
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    try {
        const page = await context.newPage();
        const started = performance.now();
        // Normal Render wake-up loading/redirect behavior is allowed. No keepalive traffic.
        const response = await page.goto(origin + '/demo/replay', { timeout: 180_000 });
        await expect(page.getByText('Synthetic demo data', { exact: true })).toBeVisible({ timeout: 180_000 });
        await expect(page.getByRole('cell', { name: 'Yes', exact: true })).toHaveCount(2);
        await expect(page.getByText('APPROVED', { exact: true })).toBeVisible();
        const visibleMs = Math.round(performance.now() - started);
        const fingerprint = hash(await page.getByRole('main').innerText());
        const ready = await context.request.get(origin + '/ready');
        expect(ready.status()).toBe(200);
        expect(ready.headers()['strict-transport-security']).toBe('max-age=31536000');
        expect(await ready.json()).toEqual({ status: 'ready' });
        if (capture) await page.screenshot({ path: output + '/cold-wake-demo.png', fullPage: true });
        return { firstStatus: response.status(), visibleMs, fingerprint, ready: ready.status(), verifiedTls: true };
    } finally {
        await context.close();
        await browser.close();
    }
}

const baseline = await readDemo(false);
const quietStart = Date.now();
console.log(JSON.stringify({ phase: 'idle_started', at: new Date(quietStart).toISOString(), baselineMs: baseline.visibleMs, requiredQuietMinutes: 16 }));
const deadline = quietStart + 16 * 60_000;
while (Date.now() < deadline) {
    await new Promise(resolve => setTimeout(resolve, Math.min(60_000, deadline - Date.now())));
    console.log(JSON.stringify({ phase: 'idle', elapsedMinutes: Math.floor((Date.now() - quietStart) / 60_000), noApplicationRequestsSent: true }));
}
const wakeStarted = new Date().toISOString();
const wake = await readDemo(true);
expect(wake.fingerprint).toBe(baseline.fingerprint);
const evidence = { origin, quietStart: new Date(quietStart).toISOString(), wakeStarted, quietMinutes: 16, baseline, wake,
    persistedSyntheticDemoUnchanged: true, providerSleepObservedSeparately: false,
    limitation: 'Measures after a real 16-minute quiet window. Dashboard sleep state is separate evidence; third-party visits cannot be excluded.' };
await writeFile(output + '/cold-start-evidence.json', JSON.stringify(evidence, null, 2));
console.log(JSON.stringify({ phase: 'complete', quietMinutes: 16, baselineMs: baseline.visibleMs, wakeMs: wake.visibleMs, persistedDemoUnchanged: true, ready: wake.ready }));
