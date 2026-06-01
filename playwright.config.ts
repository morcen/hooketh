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
