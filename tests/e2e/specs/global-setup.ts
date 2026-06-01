import { test as setup, expect } from '@playwright/test'
import { fileURLToPath } from 'url'
import path from 'path'

const authFile = path.join(path.dirname(fileURLToPath(import.meta.url)), '../.auth/user.json')

export const E2E_USER = {
    name: 'E2E Tester',
    email: 'e2e@hooketh.test',
    password: 'Password1!',
}

setup('authenticate test user', async ({ page }) => {
    await page.goto('/login')

    // Try logging in first — user may already exist
    await page.fill('#email', E2E_USER.email)
    await page.fill('#password', E2E_USER.password)
    await page.click('button[type="submit"]')
    await page.waitForURL('**/dashboard', { timeout: 6_000 }).catch(() => null)

    if (page.url().includes('/dashboard')) {
        await page.context().storageState({ path: authFile })
        return
    }

    // Register
    await page.goto('/register')
    await page.fill('#name', E2E_USER.name)
    await page.fill('#email', E2E_USER.email)
    await page.fill('#password', E2E_USER.password)
    await page.fill('#password_confirmation', E2E_USER.password)
    await page.click('button[type="submit"]')

    await expect(page).toHaveURL(/dashboard/, { timeout: 10_000 })
    await page.context().storageState({ path: authFile })
})
