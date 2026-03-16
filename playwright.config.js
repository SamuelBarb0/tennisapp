// @ts-check
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: 0,
    workers: 1,
    reporter: 'list',
    timeout: 60000,
    use: {
        baseURL: 'http://localhost/tennisapp/public',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        headless: true,
    },
    projects: [
        {
            name: 'chromium',
            use: { browserName: 'chromium' },
        },
    ],
});
