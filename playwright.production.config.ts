import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PRODUCTION_TEST_URL;
if (baseURL !== 'https://scopetwin-hizmali.onrender.com') {
    throw new Error('Explicit approved production demo URL required; local environments are never used.');
}

export default defineConfig({
    testDir: './tests/production',
    workers: 1,
    retries: 0,
    timeout: 240_000,
    expect: { timeout: 20_000 },
    use: { baseURL, trace: 'off', screenshot: 'off', video: 'off' },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 } } }],
    reporter: 'list',
});
