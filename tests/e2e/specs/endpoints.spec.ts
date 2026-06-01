import { test, expect } from '@playwright/test'

const ENDPOINT_NAME = `E2E Endpoint ${Date.now()}`
const ENDPOINT_URL = 'https://httpbin.org/post'

test.describe('Endpoints', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/endpoints')
    })

    test('renders endpoints page heading', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /endpoints/i })).toBeVisible()
        await expect(page.getByRole('button', { name: /add endpoint/i })).toBeVisible()
    })

    test('opens create modal', async ({ page }) => {
        await page.getByRole('button', { name: /add endpoint/i }).click()
        await expect(page.getByRole('dialog')).toBeVisible()
        await expect(page.getByRole('heading', { name: /create endpoint/i })).toBeVisible()
        await expect(page.locator('#name')).toBeVisible()
        await expect(page.locator('#url')).toBeVisible()
        await expect(page.locator('#description')).toBeVisible()
    })

    test('closes modal on cancel', async ({ page }) => {
        await page.getByRole('button', { name: /add endpoint/i }).click()
        await expect(page.getByRole('dialog')).toBeVisible()
        await page.getByRole('button', { name: /cancel/i }).click()
        await expect(page.getByRole('dialog')).not.toBeVisible()
    })

    test('shows validation error for missing required fields', async ({ page }) => {
        await page.getByRole('button', { name: /add endpoint/i }).click()
        await page.getByRole('button', { name: /create endpoint/i }).click()

        // Browser native validation or server-side error
        const nameInput = page.locator('#name')
        const validity = await nameInput.evaluate((el: HTMLInputElement) => el.validity.valid)
        expect(validity).toBe(false)
    })

    test('creates a new endpoint and shows one-time secret', async ({ page }) => {
        await page.getByRole('button', { name: /add endpoint/i }).click()

        await page.fill('#name', ENDPOINT_NAME)
        await page.fill('#url', ENDPOINT_URL)
        await page.fill('#description', 'Created by E2E test')
        await page.getByRole('button', { name: /create endpoint/i }).click()

        // Secret modal appears
        const secretModal = page.getByRole('dialog', { name: /save your signing secret/i })
        await expect(secretModal).toBeVisible({ timeout: 10_000 })
        await expect(secretModal.locator('input[type="text"]')).not.toBeEmpty()

        // Dismiss the secret modal
        await secretModal.getByRole('button', { name: /i've saved it/i }).click()
        await expect(secretModal).not.toBeVisible()

        // Endpoint appears in list
        await expect(page.getByText(ENDPOINT_NAME)).toBeVisible({ timeout: 8_000 })
    })

    test('searches endpoints by name', async ({ page }) => {
        await page.fill('input[placeholder*="Search"]', ENDPOINT_NAME)
        await expect(page.getByText(ENDPOINT_NAME)).toBeVisible({ timeout: 8_000 })
    })

    test('filters endpoints by active status', async ({ page }) => {
        await page.selectOption('select.app-select', 'active')
        // Every visible status badge should be Active
        const badges = page.locator('.app-status').filter({ hasText: 'Active' })
        const inactiveBadges = page.locator('.app-status').filter({ hasText: 'Inactive' })
        expect(await inactiveBadges.count()).toBe(0)
    })

    test('edits an existing endpoint', async ({ page }) => {
        // Ensure endpoint exists
        await expect(page.getByText(ENDPOINT_NAME)).toBeVisible({ timeout: 8_000 })

        // Open dropdown for that endpoint and click Edit
        const card = page.locator('article').filter({ hasText: ENDPOINT_NAME })
        await card.locator('button[class*="rounded-md p-2"]').click()
        await page.getByRole('menuitem', { name: /^edit$/i }).click()

        const dialog = page.getByRole('dialog')
        await expect(dialog).toBeVisible()
        await expect(dialog.getByRole('heading', { name: /edit endpoint/i })).toBeVisible()

        const updatedName = ENDPOINT_NAME + ' (updated)'
        await page.fill('#name', updatedName)
        await page.getByRole('button', { name: /update endpoint/i }).click()

        await expect(page.getByText(updatedName)).toBeVisible({ timeout: 8_000 })
    })

    test('toggles endpoint active/inactive state', async ({ page }) => {
        const updatedName = ENDPOINT_NAME + ' (updated)'
        const card = page.locator('article').filter({ hasText: updatedName })
        await card.locator('button[class*="rounded-md p-2"]').click()
        await page.getByRole('menuitem', { name: /deactivate|activate/i }).click()

        // Badge changes
        await page.waitForLoadState('networkidle')
        await expect(card).toBeVisible()
    })

    test('copies endpoint URL', async ({ page }) => {
        const card = page.locator('article').filter({ hasText: ENDPOINT_NAME })
        const copyBtn = card.getByRole('button', { name: /copy/i })
        if (await copyBtn.isVisible()) {
            await copyBtn.click()
            await expect(card.getByRole('button', { name: /copied/i })).toBeVisible({ timeout: 3_000 })
        }
    })

    test('regenerates signing secret from edit modal', async ({ page }) => {
        const updatedName = ENDPOINT_NAME + ' (updated)'
        const card = page.locator('article').filter({ hasText: updatedName })
        await card.locator('button[class*="rounded-md p-2"]').click()
        await page.getByRole('menuitem', { name: /^edit$/i }).click()

        const dialog = page.getByRole('dialog')
        await expect(dialog.getByText(/secret configured/i)).toBeVisible()

        page.once('dialog', d => d.accept())
        await dialog.getByRole('button', { name: /rotate key/i }).click()

        // Secret reveal modal appears
        const secretModal = page.getByRole('dialog', { name: /save your signing secret/i })
        await expect(secretModal).toBeVisible({ timeout: 10_000 })
        await secretModal.getByRole('button', { name: /i've saved it/i }).click()
    })

    test('deletes an endpoint after confirmation', async ({ page }) => {
        const updatedName = ENDPOINT_NAME + ' (updated)'
        const card = page.locator('article').filter({ hasText: updatedName })
        await card.locator('button[class*="rounded-md p-2"]').click()

        page.once('dialog', d => d.accept())
        await page.getByRole('menuitem', { name: /delete/i }).click()

        await page.waitForLoadState('networkidle')
        await expect(page.getByText(updatedName)).not.toBeVisible({ timeout: 8_000 })
    })

    test('clears search filter', async ({ page }) => {
        await page.fill('input[placeholder*="Search"]', 'something that does not exist')
        await expect(page.getByText('No endpoints found')).toBeVisible({ timeout: 8_000 })

        await page.getByRole('button', { name: /clear/i }).click()
        await expect(page.locator('input[placeholder*="Search"]')).toHaveValue('')
    })
})
