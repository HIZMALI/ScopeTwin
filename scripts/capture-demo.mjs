import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';

// Fresh anonymous browser, fixed local rehearsal, public pages only. No credentials.
mkdirSync('docs/images', { recursive: true });
const browser = await chromium.launch();
try {
    for (const width of [1440, 390]) {
        const page = await browser.newPage({ viewport: { width, height: 1000 } });
        for (const [path, name] of [['/', 'landing'], ['/demo/replay', 'demo']]) {
            const response = await page.goto(`http://127.0.0.1:18733${path}`);
            if (response.status() !== 200) throw new Error('Rehearsal unavailable');
            await page.getByRole('heading', { level: 1 }).waitFor();
            if (name === 'demo') await page.getByText('Synthetic demo data', { exact: true }).waitFor();
            if (/st_[a-f0-9]{16}_[a-f0-9]{64}|key_hash/.test(await page.content())) throw new Error('Unsafe screenshot content');
            await page.screenshot({ path: `docs/images/${name}-${width === 1440 ? 'desktop' : 'mobile'}.png`, fullPage: true });
        }
        await page.close();
    }
} finally { await browser.close(); }
console.log('Captured four actual anonymous landing/demo screenshots, with no credentials.');
