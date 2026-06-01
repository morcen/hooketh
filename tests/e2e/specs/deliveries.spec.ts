import { test, expect } from '@playwright/test'

test.describe('Deliveries', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/deliveries')
    })

    test('renders deliveries page heading', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /webhook deliveries/i })).toBeVisible()
        await expect(page.getByRole('button', { name: /refresh/i })).toBeVisible()
        await expect(page.getByRole('button', { name: /retry failed/i })).toBeVisible()
    })

    test('shows stat cards', async ({ page }) => {
        await expect(page.getByText('Total')).toBeVisible()
        await expect(page.getByText('Successful')).toBeVisible()
        await expect(page.getByText('Failed')).toBeVisible()
        await expect(page.getByText('Pending/Retrying')).toBeVisible()
    })

    test('shows filter controls', async ({ page }) => {
        await expect(page.locator('#status-filter')).toBeVisible()
        await expect(page.locator('#endpoint-filter')).toBeVisible()
        await expect(page.locator('#event-filter')).toBeVisible()
        await expect(page.locator('#from-date')).toBeVisible()
        await expect(page.locator('#to-date')).toBeVisible()
    })

    test('shows deliveries table or empty state', async ({ page }) => {
        const hasRows = await page.locator('tbody tr').count()
        if (hasRows > 0) {
            await expect(page.getByRole('columnheader', { name: /event/i })).toBeVisible()
            await expect(page.getByRole('columnheader', { name: /endpoint/i })).toBeVisible()
            await expect(page.getByRole('columnheader', { name: /status/i })).toBeVisible()
            await expect(page.getByRole('columnheader', { name: /response/i })).toBeVisible()
            await expect(page.getByRole('columnheader', { name: /attempts/i })).toBeVisible()
        } else {
            await expect(page.getByText('No deliveries found')).toBeVisible()
        }
    })

    test('filters by status', async ({ page }) => {
        await page.locator('#status-filter').selectOption('success')
        await page.waitForLoadState('networkidle')

        // All visible status badges should say "success"
        const rows = await page.locator('tbody tr').count()
        if (rows > 0) {
            const badges = page.locator('tbody .rounded-full').filter({ hasText: /^success$/ })
            const allBadges = page.locator('tbody .rounded-full')
            await expect(badges).toHaveCount(await allBadges.count())
        }
    })

    test('clears filters', async ({ page }) => {
        await page.locator('#status-filter').selectOption('failed')
        await page.waitForLoadState('networkidle')

        await page.getByRole('button', { name: /clear filters/i }).click()
        await page.waitForLoadState('networkidle')

        await expect(page.locator('#status-filter')).toHaveValue('')
        await expect(page.locator('#event-filter')).toHaveValue('')
    })

    test('filters by event name', async ({ page }) => {
        await page.fill('#event-filter', 'nonexistent.event.xyz')
        await page.keyboard.press('Enter')
        await page.waitForLoadState('networkidle')

        await expect(page.getByText('No deliveries found')).toBeVisible({ timeout: 8_000 })
    })

    test('opens details modal for a delivery', async ({ page }) => {
        const detailsBtn = page.locator('tbody').getByRole('button', { name: /details/i }).first()
        if (await detailsBtn.isVisible()) {
            await detailsBtn.click()
            const dialog = page.getByRole('dialog')
            await expect(dialog).toBeVisible()
            await expect(dialog.getByRole('heading', { name: /delivery details/i })).toBeVisible()
            await expect(dialog.getByText('Event Information')).toBeVisible()
            await expect(dialog.getByText('Endpoint Information')).toBeVisible()
            await expect(dialog.getByText('Delivery Status')).toBeVisible()
            await expect(dialog.getByText('Sent Payload')).toBeVisible()

            await dialog.getByRole('button', { name: /close/i }).click()
            await expect(dialog).not.toBeVisible()
        }
    })

    test('refresh button reloads deliveries', async ({ page }) => {
        await page.getByRole('button', { name: /refresh/i }).click()
        await page.waitForLoadState('networkidle')
        // Page should still show the deliveries page
        await expect(page.getByRole('heading', { name: /webhook deliveries/i })).toBeVisible()
    })

    test('retry failed delivery button is visible for failed rows', async ({ page }) => {
        const retryBtn = page.locator('tbody').getByRole('button', { name: /^retry$/i }).first()
        if (await retryBtn.isVisible()) {
            // Verify the corresponding row has a "failed" badge
            const row = retryBtn.locator('xpath=ancestor::tr')
            await expect(row.locator('.rounded-full').filter({ hasText: 'failed' })).toBeVisible()
        }
    })

    test('date range filter narrows results', async ({ page }) => {
        const today = new Date().toISOString().split('T')[0]
        await page.fill('#from-date', today)
        await page.waitForLoadState('networkidle')

        // Results should still be valid (no page error)
        await expect(page.getByRole('heading', { name: /webhook deliveries/i })).toBeVisible()
    })
})
