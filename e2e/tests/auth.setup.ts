import { test as setup, expect } from '@playwright/test'
import fs from 'node:fs'
import path from 'node:path'

import { loginViaUi, expectAppShell } from '../src/helpers/ui'

const authFile = path.join(__dirname, '..', '.auth', 'owner.json')

setup('authenticate owner', async ({ page }) => {
  fs.mkdirSync(path.dirname(authFile), { recursive: true })
  await loginViaUi(page)
  await expectAppShell(page)
  await expect(page).toHaveURL(/\/app(\/|$)/)
  await page.context().storageState({ path: authFile })
})
