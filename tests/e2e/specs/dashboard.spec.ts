import { test, expect } from '@playwright/test'

test.describe('Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/dashboard')
    })

    test('renders dashboard heading and stats', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /webhook dashboard/i })).toBeVisible()
        await expect(page.getByText('Endpoints')).toBeVisible()
        await expect(page.getByText('Events')).toBeVisible()
        await expect(page.getByText('Deliveries')).toBeVisible()
        await expect(page.getByText('Success rate')).toBeVisible()
    })

    test('shows setup checklist', async ({ page }) => {
        await expect(page.getByText('Setup checklist')).toBeVisible()
        await expect(page.getByText('Create endpoint targets')).toBeVisible()
        await expect(page.getByText('Define events')).toBeVisible()
        await expect(page.getByText('Send and inspect')).toBeVisible()
    })

    test('navigates to endpoints from dashboard button', async ({ page }) => {
        await page.getByRole('link', { name: /add endpoint/i }).click()
        await expect(page).toHaveURL(/endpoints/)
    })

    test('navigates to events from dashboard button', async ({ page }) => {
        await page.getByRole('link', { name: /manage events/i }).click()
        await expect(page).toHaveURL(/events/)
    })

    test('navigation links work', async ({ page }) => {
        await page.getByRole('link', { name: /^endpoints$/i }).click()
        await expect(page).toHaveURL(/endpoints/)

        await page.getByRole('link', { name: /^events$/i }).click()
        await expect(page).toHaveURL(/events/)

        await page.getByRole('link', { name: /^deliveries$/i }).click()
        await expect(page).toHaveURL(/deliveries/)

        await page.getByRole('link', { name: /^dashboard$/i }).click()
        await expect(page).toHaveURL(/dashboard/)
    })

    test('recent deliveries table or empty state is shown', async ({ page }) => {
        const hasDeliveries = await page.locator('table').isVisible()
        if (hasDeliveries) {
            await expect(page.getByRole('columnheader', { name: /event/i })).toBeVisible()
            await expect(page.getByRole('columnheader', { name: /endpoint/i })).toBeVisible()
            await expect(page.getByRole('columnheader', { name: /status/i })).toBeVisible()
        } else {
            await expect(page.getByText('No deliveries yet')).toBeVisible()
        }
    })

    test('health endpoint returns healthy status', async ({ request }) => {
        const response = await request.get('/health')
        expect(response.status()).toBe(200)
        const body = await response.json()
        expect(body).toHaveProperty('database')
        expect(body).toHaveProperty('redis')
    })
})
