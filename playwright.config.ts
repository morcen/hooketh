import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
    testDir: './tests/e2e/specs',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: [['html', { open: 'never' }], ['list']],
    timeout: 30_000,
    expect: { timeout: 8_000 },

    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },

    // Self-starts the app against the SQLite database prepared by the
    // e2e CI job (see .github/workflows/ci.yml), so the suite can also be
    // run locally with a single `npm run test:e2e` once the app has been
    // migrated. Set PLAYWRIGHT_BASE_URL to point at an already-running
    // server (e.g. `make up`) to skip this and reuse it instead.
    webServer: process.env.PLAYWRIGHT_BASE_URL ? undefined : {
        command: 'php artisan serve --host=127.0.0.1 --port=8080',
        url: 'http://127.0.0.1:8080/health',
        reuseExistingServer: !process.env.CI,
        timeout: 30_000,
    },

    projects: [
        {
            name: 'setup',
            testMatch: /global-setup\.ts/,
        },
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'tests/e2e/.auth/user.json',
            },
            dependencies: ['setup'],
            testIgnore: /global-setup\.ts/,
        },
    ],
})
