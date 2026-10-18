import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/browser',
    workers: 1,
    timeout: 90000,
    globalSetup: './tests/browser/setup.ts',
    use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 1000 },
        baseURL: 'http://127.0.0.1:18732',
        trace: 'off',
        screenshot: 'off',
    },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=18732 --no-reload',
        url: 'http://127.0.0.1:18732/up',
        reuseExistingServer: false,
        env: {
            DB_CONNECTION: 'pgsql',
            DB_DATABASE: 'scopetwin_browser_test',
            APP_URL: 'http://127.0.0.1:18732',
            APP_ENV: 'local',
            APP_DEBUG: 'false',
            BACKBOARD_ENABLED: 'false',
            BACKBOARD_API_KEY: '',
            SESSION_COOKIE: 'scopetwin_browser_test_session',
        },
    },
    reporter: 'list',
});
