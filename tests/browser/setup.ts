import { execFileSync } from 'node:child_process';

export default function setup() {
    // Fixed disposable database; never migrate:fresh/reset the user's application database.
    const args = ['compose', 'exec', '-T', 'postgres', 'psql', '-U', 'scopetwin', '-d', 'postgres'];
    const exists = execFileSync('docker', [...args, '-tAc', "SELECT 1 FROM pg_database WHERE datname = 'scopetwin_browser_test'"], {
        encoding: 'utf8',
        windowsHide: true,
    }).trim();
    if (exists !== '1') execFileSync('docker', [...args, '-c', 'CREATE DATABASE scopetwin_browser_test;'], { windowsHide: true });
    execFileSync('php', ['artisan', 'migrate', '--force'], {
        env: { ...process.env, DB_CONNECTION: 'pgsql', DB_DATABASE: 'scopetwin_browser_test' },
        windowsHide: true,
    });
}
