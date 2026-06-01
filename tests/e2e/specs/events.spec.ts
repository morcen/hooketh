import { test, expect } from '@playwright/test'

const EVENT_NAME = `e2e.test.${Date.now()}`
const EVENT_TYPE = 'e2e.type'

test.describe('Events', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/events')
    })

    test('renders events page heading and create button', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /manage events/i })).toBeVisible()
        await expect(page.getByRole('button', { name: /create event/i })).toBeVisible()
    })

    test('opens create event modal', async ({ page }) => {
        await page.getByRole('button', { name: /create event/i }).click()
        const dialog = page.getByRole('dialog')
        await expect(dialog).toBeVisible()
        await expect(dialog.getByRole('heading', { name: /create new event/i })).toBeVisible()
        await expect(page.locator('#name')).toBeVisible()
        await expect(page.locator('#event_type')).toBeVisible()
        await expect(page.locator('#description')).toBeVisible()
        await expect(page.locator('#payload')).toBeVisible()
        await expect(page.locator('#schema')).toBeVisible()
    })

    test('closes modal on cancel', async ({ page }) => {
        await page.getByRole('button', { name: /create event/i }).click()
        await expect(page.getByRole('dialog')).toBeVisible()
        await page.getByRole('button', { name: /cancel/i }).click()
        await expect(page.getByRole('dialog')).not.toBeVisible()
    })

    test('creates a new event', async ({ page }) => {
        await page.getByRole('button', { name: /create event/i }).click()

        await page.fill('#name', EVENT_NAME)
        await page.fill('#event_type', EVENT_TYPE)
        await page.fill('#description', 'Created by E2E test suite')
        await page.fill('#payload', JSON.stringify({ user_id: 1, email: 'test@example.com' }))
        await page.getByRole('button', { name: /^create$/i }).click()

        await expect(page.getByText(EVENT_NAME)).toBeVisible({ timeout: 10_000 })
    })

    test('searches events by name', async ({ page }) => {
        await page.fill('input[placeholder*="Search"]', EVENT_NAME)
        await expect(page.getByText(EVENT_NAME)).toBeVisible({ timeout: 8_000 })

        await page.fill('input[placeholder*="Search"]', 'xxxxxxxxxnotexistxxx')
        await expect(page.getByText(/no events found/i)).toBeVisible({ timeout: 5_000 })
    })

    test('filters events by type', async ({ page }) => {
        const typeSelect = page.locator('select').first()
        const options = await typeSelect.locator('option').allTextContents()

        if (options.some(o => o === EVENT_TYPE)) {
            await typeSelect.selectOption(EVENT_TYPE)
            await expect(page.getByText(EVENT_NAME)).toBeVisible({ timeout: 8_000 })
        }
    })

    test('opens edit page for an event', async ({ page }) => {
        const eventCard = page.locator('.bg-white').filter({ hasText: EVENT_NAME }).first()
        await eventCard.locator('button').filter({ hasText: '' }).last().click()

        await page.getByRole('link', { name: /edit event/i }).click()
        await expect(page).toHaveURL(/events\/\d+\/edit/)
    })

    test('edit page shows event fields', async ({ page }) => {
        await page.goto('/events')
        const eventCard = page.locator('.bg-white').filter({ hasText: EVENT_NAME }).first()
        await eventCard.locator('button').filter({ hasText: '' }).last().click()
        await page.getByRole('link', { name: /edit event/i }).click()

        await expect(page.locator('#name')).toHaveValue(EVENT_NAME)
        await expect(page.locator('#event_type')).toHaveValue(EVENT_TYPE)
    })

    test('updates an event from the edit page', async ({ page }) => {
        await page.goto('/events')
        const eventCard = page.locator('.bg-white').filter({ hasText: EVENT_NAME }).first()
        await eventCard.locator('button').filter({ hasText: '' }).last().click()
        await page.getByRole('link', { name: /edit event/i }).click()

        await page.fill('#description', 'Updated by E2E test')
        await page.getByRole('button', { name: /save|update/i }).click()

        await expect(page).toHaveURL(/events/, { timeout: 10_000 })
    })

    test('opens manage endpoints modal', async ({ page }) => {
        const eventCard = page.locator('.bg-white').filter({ hasText: EVENT_NAME }).first()
        await eventCard.locator('button').filter({ hasText: '' }).last().click()
        await page.getByRole('menuitem', { name: /manage endpoints/i }).click()

        const dialog = page.getByRole('dialog')
        await expect(dialog).toBeVisible()
        await expect(dialog.getByRole('heading', { name: /manage endpoints/i })).toBeVisible()
        await page.getByRole('button', { name: /cancel/i }).click()
    })

    test('opens trigger event modal with prefilled payload', async ({ page }) => {
        const eventCard = page.locator('.bg-white').filter({ hasText: EVENT_NAME }).first()
        await eventCard.locator('button').filter({ hasText: '' }).last().click()
        await page.getByRole('menuitem', { name: /trigger event/i }).click()

        const dialog = page.getByRole('dialog')
        await expect(dialog).toBeVisible()
        await expect(dialog.getByRole('heading', { name: /trigger event/i })).toBeVisible()
        await expect(page.locator('#trigger-payload')).not.toBeEmpty()
        await page.getByRole('button', { name: /cancel/i }).click()
    })

    test('duplicates an event', async ({ page }) => {
        const eventCard = page.locator('.bg-white').filter({ hasText: EVENT_NAME }).first()
        await eventCard.locator('button').filter({ hasText: '' }).last().click()
        await page.getByRole('menuitem', { name: /duplicate/i }).click()

        const dialog = page.getByRole('dialog')
        await expect(dialog).toBeVisible()
        await expect(page.locator('#name')).toHaveValue(EVENT_NAME + ' (Copy)')
        await page.getByRole('button', { name: /cancel/i }).click()
    })

    test('navigates to deliveries filtered by event', async ({ page }) => {
        const eventCard = page.locator('.bg-white').filter({ hasText: EVENT_NAME }).first()
        await eventCard.locator('button').filter({ hasText: '' }).last().click()
        await page.getByRole('menuitem', { name: /view deliveries/i }).click()

        await expect(page).toHaveURL(/deliveries/)
    })

    test('deletes an event after confirmation', async ({ page }) => {
        const eventCard = page.locator('.bg-white').filter({ hasText: EVENT_NAME }).first()
        await eventCard.locator('button').filter({ hasText: '' }).last().click()

        page.once('dialog', d => d.accept())
        await page.getByRole('menuitem', { name: /delete/i }).click()

        await page.waitForLoadState('networkidle')
        await expect(page.getByText(EVENT_NAME)).not.toBeVisible({ timeout: 8_000 })
    })
})
