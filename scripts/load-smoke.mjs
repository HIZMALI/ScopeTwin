import { performance } from 'node:perf_hooks';
import { writeFileSync } from 'node:fs';
import process from 'node:process';

// Deliberately fixed loopback target; never stress the existing user app or an external site.
const base = process.env.RELEASE_TEST_URL ?? 'http://127.0.0.1:18733';
assertLoopback(base);
function assertLoopback(url) { if (!['http://127.0.0.1:18733', 'http://127.0.0.1:18734'].includes(url)) throw new Error('Fixed isolated loopback target required.'); }
const target = base + '/demo/replay';
const latencies = [];
const failures = [];
let next = 0;
await Promise.all(Array.from({ length: 4 }, async () => {
    while (next++ < 40) {
        const start = performance.now();
        try {
            const result = await fetch(target, { signal: AbortSignal.timeout(10_000) });
            const text = await result.text();
            if (result.status !== 200 || !text.includes('public-root')) failures.push(result.status);
        } catch { failures.push('network'); }
        latencies.push(performance.now() - start);
    }
}));
latencies.sort((a, b) => a - b);
const result = { target, requests: latencies.length, concurrency: 4, failures: failures.length, errorRate: failures.length / latencies.length, p50Ms: +latencies[Math.ceil(latencies.length * .5) - 1].toFixed(2), p95Ms: +latencies[Math.ceil(latencies.length * .95) - 1].toFixed(2), maxMs: +latencies.at(-1).toFixed(2), timestamp: new Date().toISOString() };
writeFileSync(`storage/${base.endsWith('18734') ? 'build035' : 'build03'}-load.json`, JSON.stringify(result, null, 2));
console.log(JSON.stringify(result));
if (failures.length) process.exitCode = 1;
