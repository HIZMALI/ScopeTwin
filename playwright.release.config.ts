import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/release',
    workers: 1,
    timeout: 120_000,
    use: {
        baseURL: process.env.RELEASE_TEST_URL ?? 'http://127.0.0.1:18733',
        viewport: { width: 1440, height: 1000 },
        trace: 'off',
        screenshot: 'off',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 } } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'], viewport: { width: 1440, height: 1000 } } },
        { name: 'webkit', use: { ...devices['Desktop Safari'], viewport: { width: 1440, height: 1000 } } },
    ],
    reporter: 'list',
});
