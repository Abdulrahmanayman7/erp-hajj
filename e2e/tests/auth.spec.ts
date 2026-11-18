import { test, expect } from '@playwright/test'

import { loginViaUi, expectAppShell } from '../src/helpers/ui'
import { e2eEnv } from '../src/env'

test.describe('Authentication UI', () => {
  test.use({ storageState: { cookies: [], origins: [] } })

  test('login reaches tenant app shell', async ({ page }) => {
    await loginViaUi(page)
    await expectAppShell(page)
    await expect(page).toHaveURL(/\/app(\/|$)/)
  })

  test('invalid password stays on login', async ({ page }) => {
    await page.goto('/login')
    await page.locator('#login-email').fill(e2eEnv.email())
    await page.locator('#login-password').fill('WrongPassword!999')
    await page.locator('button[type="submit"]').click()
    await expect(page).toHaveURL(/\/login/)
    await expect(page.locator('#login-email')).toBeVisible()
  })
})
