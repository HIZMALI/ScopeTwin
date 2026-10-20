import { expect, test } from '@playwright/test';

test('public HTTPS boundary: redirects, certificate, private paths, host and unauthenticated writes', async ({ request, baseURL }) => {
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
    const wrongHost = await request.get('/', { headers: { Host: 'untrusted.example.test' }, maxRedirects: 0 });
    expect([400, 403, 404, 421]).toContain(wrongHost.status());
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
});
