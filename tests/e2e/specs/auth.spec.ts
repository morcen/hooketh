import { test, expect } from '@playwright/test'

// Auth tests run without pre-authenticated state
test.use({ storageState: { cookies: [], origins: [] } })

test.describe('Authentication', () => {
    test('login page renders', async ({ page }) => {
        await page.goto('/login')
        await expect(page.locator('#email')).toBeVisible()
        await expect(page.locator('#password')).toBeVisible()
        await expect(page.getByRole('button', { name: /log in/i })).toBeVisible()
    })

    test('redirects unauthenticated users to login', async ({ page }) => {
        await page.goto('/dashboard')
        await expect(page).toHaveURL(/login/)
    })

    test('shows error on invalid credentials', async ({ page }) => {
        await page.goto('/login')
        await page.fill('#email', 'nobody@example.com')
        await page.fill('#password', 'wrongpassword')
        await page.click('button[type="submit"]')

        await expect(page.locator('text=/credentials|provided/i')).toBeVisible({ timeout: 8_000 })
    })

    test('logs in with valid credentials', async ({ page }) => {
        await page.goto('/login')
        await page.fill('#email', 'e2e@hooketh.test')
        await page.fill('#password', 'Password1!')
        await page.click('button[type="submit"]')
        await expect(page).toHaveURL(/dashboard/, { timeout: 10_000 })
    })

    test('register page renders required fields', async ({ page }) => {
        await page.goto('/register')
        await expect(page.locator('#name')).toBeVisible()
        await expect(page.locator('#email')).toBeVisible()
        await expect(page.locator('#password')).toBeVisible()
        await expect(page.locator('#password_confirmation')).toBeVisible()
    })

    test('shows validation error when passwords do not match', async ({ page }) => {
        await page.goto('/register')
        await page.fill('#name', 'Test User')
        await page.fill('#email', 'mismatch@hooketh.test')
        await page.fill('#password', 'Password1!')
        await page.fill('#password_confirmation', 'Different1!')
        await page.click('button[type="submit"]')

        await expect(page.locator('text=/password/i').first()).toBeVisible({ timeout: 8_000 })
    })

    test('logs out successfully', async ({ page }) => {
        await page.goto('/login')
        await page.fill('#email', 'e2e@hooketh.test')
        await page.fill('#password', 'Password1!')
        await page.click('button[type="submit"]')
        await page.waitForURL('**/dashboard', { timeout: 10_000 })

        // Open user dropdown and click log out
        await page.getByRole('navigation').getByRole('button').first().click()
        const logoutBtn = page.getByRole('button', { name: /log out/i })
        await logoutBtn.waitFor({ timeout: 5_000 })
        await logoutBtn.click()

        await expect(page).toHaveURL(/login/, { timeout: 10_000 })
    })
})
