import { expect, test } from '@playwright/test';
import { request as httpsRequest } from 'node:https';

test('public HTTPS boundary: redirects, certificate, private paths, host and unauthenticated writes', async ({ page, request, baseURL }) => {
    // TLS verification remains enabled in Playwright; no ignoreHTTPSErrors.
    const ready = await request.get('/ready');
    expect(ready.status()).toBe(200);
    expect(await ready.json()).toEqual({ status: 'ready' });
    const redirect = await request.get(baseURL!.replace('https:', 'http:') + '/demo', { maxRedirects: 0 });
    expect([301, 302, 307, 308]).toContain(redirect.status());
    expect(redirect.headers().location).toBe(baseURL + '/demo');
    const landing = await request.get('/');
    expect(landing.headers()['strict-transport-security']).toContain('max-age=');
    expect(landing.headers()['content-security-policy']).toContain("frame-ancestors 'none'");
    expect(landing.headers()['x-content-type-options']).toBe('nosniff');
    for (const path of ['/.env', '/.env.production', '/.git/config', '/storage/logs/laravel.log', '/artisan', '/composer.lock', '/deploy/verify-runtime.php']) {
        const response = await request.get(path);
        expect([403, 404]).toContain(response.status());
        expect(await response.text()).not.toMatch(/APP_KEY=|DB_PASSWORD=|\[core\]|vendor\/autoload/);
    }
    // Pin correct TLS SNI while testing an incorrect HTTP Host. Playwright otherwise
    // uses Host as SNI and only exercises the edge's TLS rejection.
    const wrongHost = await new Promise<number>((resolve, reject) => {
        const probe = httpsRequest(baseURL!, { servername: new URL(baseURL!).hostname, headers: { Host: 'untrusted.example.test' } }, response => {
            response.resume();
            resolve(response.statusCode!);
        });
        probe.setTimeout(20_000, () => probe.destroy(new Error('Host probe timed out')));
        probe.on('error', reject);
        probe.end();
    });
    expect([400, 403, 404, 421]).toContain(wrongHost);
    const forwarded = await request.get('/demo', { headers: { 'X-Forwarded-Host': 'untrusted.example.test', 'X-Forwarded-Proto': 'http' } });
    expect(forwarded.status()).toBe(200);
    expect(await forwarded.text()).not.toContain('untrusted.example.test');
    const registration = await request.get('/register');
    expect(await registration.text()).not.toContain('Forgot your password?');
    const privateApi = await request.post('/api/v1/agent/actions/evaluate', { data: { action_name: 'unknown.action' } });
    expect(privateApi.status()).toBe(401);
    const demoBefore = await request.get('/demo/replay');
    expect((await request.post('/demo/replay')).status()).toBe(405);
    expect((await request.delete('/demo')).status()).toBe(405);
    const demoAfter = await request.get('/demo/replay');
    // CSP nonces differ per request; compare rendered deterministic report data separately in browser tests.
    expect(demoBefore.status()).toBe(200);
    expect(demoAfter.status()).toBe(200);
    await page.goto('/forgot-password');
    await expect(page.getByRole('status')).toContainText('Password recovery is unavailable');
    await expect(page.getByRole('button', { name: 'Email password reset link' })).toHaveCount(0);
    await page.goto('/register');
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const registrationStatuses = [];
    for (let i = 1; i <= 6; i++) {
        const response = await page.request.post('/register', {
            headers: { 'X-CSRF-TOKEN': csrf!, Accept: 'application/json', 'X-Forwarded-For': `198.51.100.${i}` },
            data: {}, // Invalid input creates no users; all six attempts must share the real rate limit.
        });
        registrationStatuses.push(response.status());
    }
    expect(registrationStatuses.every(status => status === 422 || status === 429), JSON.stringify(registrationStatuses)).toBe(true);
    expect(registrationStatuses).toContain(429);
    const edgeSpoof = await page.request.post('/register', {
        headers: { 'X-CSRF-TOKEN': csrf!, Accept: 'application/json', 'CF-Connecting-IP': '198.51.100.99',
            'True-Client-IP': '198.51.100.99', 'X-Real-IP': '198.51.100.99', 'X-Forwarded-For': '198.51.100.99' },
        data: {},
    });
    expect([403, 429]).toContain(edgeSpoof.status()); // Edge rejection or the same exhausted app limit.
});
