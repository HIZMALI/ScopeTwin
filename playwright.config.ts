import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/browser',
    workers: 1,
    timeout: 90000,
    use: { baseURL: 'http://127.0.0.1:18731', ...devices['Desktop Chrome'], trace: 'off', screenshot: 'off' },
    reporter: 'list',
});
