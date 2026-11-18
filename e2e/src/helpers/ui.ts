import { expect, type Page } from '@playwright/test'

import { e2eEnv } from '../env'

export async function loginViaUi(
  page: Page,
  email = e2eEnv.email(),
  password = e2eEnv.password(),
): Promise<void> {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await page.waitForSelector('#login-email', { timeout: 30_000 })
  await page.locator('#login-email').fill(email)
  await page.locator('#login-password').fill(password)
  await page.locator('button[type="submit"]').click()
  await page.waitForURL((url) => /\/(app|platform)(\/|$)/.test(url.pathname), {
    timeout: 45_000,
  })
  expect(page.url()).toContain('/app')
}

export async function expectAppShell(page: Page): Promise<void> {
  await expect(page.locator('aside, nav, [data-test="app-sidebar"]').first()).toBeVisible({
    timeout: 20_000,
  })
}

export async function gotoApp(page: Page, path: string): Promise<void> {
  const normalized = path.startsWith('/') ? path : `/${path}`
  await page.goto(normalized, { waitUntil: 'domcontentloaded' })
  // Prefer a short settle over networkidle — SPA polling can keep the network busy.
  await page.waitForTimeout(500)
}

export async function expectNoFatalCrash(page: Page): Promise<void> {
  await expect(page.locator('body')).toBeVisible()
  const blank = await page.locator('body').innerText()
  expect(blank.trim().length).toBeGreaterThan(0)
  await expect(page.getByText(/Something went wrong|Unhandled|Internal Server Error/i)).toHaveCount(
    0,
  )
}

export async function openCreateCta(page: Page, name: RegExp | string): Promise<void> {
  const cta = page.getByRole('link', { name }).or(page.getByRole('button', { name })).first()
  await expect(cta).toBeVisible({ timeout: 15_000 })
  await cta.click()
}

export async function fillLabeledInput(page: Page, label: string, value: string): Promise<void> {
  const field = page.locator('label').filter({ hasText: label }).locator('input, textarea').first()
  await field.fill(value)
}

export async function selectLabeledOption(
  page: Page,
  label: string,
  optionText: string,
): Promise<void> {
  const container = page.locator('label').filter({ hasText: label }).first()
  const trigger = container.locator('button[aria-haspopup="listbox"]').first()
  await trigger.click()
  await page.waitForSelector('[data-app-select-menu]', { timeout: 10_000 })
  await page
    .locator('[data-app-select-menu]')
    .last()
    .getByText(optionText, { exact: false })
    .first()
    .click()
}
