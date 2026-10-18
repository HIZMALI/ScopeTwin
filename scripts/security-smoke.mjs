import assert from 'node:assert/strict';
import { writeFileSync } from 'node:fs';
import { request as httpRequest } from 'node:http';
import process from 'node:process';

const base = process.env.RELEASE_TEST_URL ?? 'http://127.0.0.1:18733';
assert.ok(['http://127.0.0.1:18733', 'http://127.0.0.1:18734'].includes(base));
const results = [];
for (const path of ['/.env', '/.git/HEAD', '/storage/logs/laravel.log', '/vendor/autoload.php', '/tests/Feature/Build03Test.php', '/node_modules/package.json']) {
    const response = await fetch(base + path);
    const body = await response.text();
    assert.ok([403,404].includes(response.status), `Private path was not denied: ${path}`);
    assert.ok(!body.includes('APP_KEY=') && !body.includes('Stack trace'));
    results.push({ path, status: response.status });
}
// Fetch implementations may normalize the forbidden Host header; send it with raw HTTP.
const hostStatus = await new Promise((resolve, reject) => {
    const req = httpRequest(base + '/ready', { headers: { Host: 'untrusted.example' } }, response => {
        response.resume();
        resolve(response.statusCode);
    });
    req.on('error', reject);
    req.end();
});
assert.equal(hostStatus, 400, 'Untrusted Host was accepted');
const ready = await fetch(base + '/ready');
assert.deepEqual(await ready.json(), { status: 'ready' });
assert.equal(ready.headers.get('strict-transport-security'), null);
const login = await fetch(base + '/login');
const cookies = login.headers.getSetCookie();
const session = cookies.find(c => c.startsWith('scopetwin_rehearsal_session='));
assert.ok(session && /httponly/i.test(session) && /samesite=lax/i.test(session));
assert.ok(!cookies.some(c => /judge-fixture|st_[a-f0-9]{16}_/.test(c)));
const csrf = await fetch(base + '/register', { method: 'POST', headers: { Origin: 'https://untrusted.example', 'Sec-Fetch-Site': 'cross-site', Accept: 'application/json' }, body: '{}' });
assert.equal(csrf.status, 419);
const demo = await fetch(base + '/demo');
assert.equal(demo.headers.getSetCookie().length, 0);
const result = { results, forgedHost: hostStatus, forgedCsrf: csrf.status, readiness: ready.status, sessionHttpOnly: true, sessionSameSite: 'lax', secureCookie: 'loopback exception; real HTTPS not tested', demoCookies: 0, timestamp: new Date().toISOString() };
writeFileSync(`storage/${base.endsWith('18734') ? 'build035' : 'build03'}-security-http.json`, JSON.stringify(result, null, 2));
console.log(JSON.stringify(result));
