import { createApp, defineComponent, h, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, describe, expect, it } from 'vitest'

import { ar } from '@/locales/ar'
import { clearConfirmation, useConfirm } from '@/shared/composables/useConfirm'

import AppConfirmDialog from './AppConfirmDialog.vue'

const cleanups: Array<() => void> = []
afterEach(() => {
  clearConfirmation()
  cleanups.splice(0).forEach((fn) => fn())
})

async function mountDialog() {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp(
    defineComponent({
      setup() {
        return () => h(AppConfirmDialog)
      },
    }),
  )
  app.use(createI18n({ legacy: false, locale: 'ar', messages: { ar } }))
  app.mount(host)
  cleanups.push(() => {
    app.unmount()
    host.remove()
  })
  await nextTick()
  return host
}

describe('AppConfirmDialog a11y', () => {
  it('focuses the accept control and exposes a focus-visible close control when opened', async () => {
    await mountDialog()
    const { confirm } = useConfirm()
    void confirm({ title: 'تأكيد', message: 'هل تريد المتابعة؟', variant: 'primary' })
    await nextTick()
    await nextTick()

    const accept = document.querySelector('[data-test="app-confirm-accept"]') as HTMLButtonElement | null
    const close = document.querySelector('[data-test="app-confirm-close"]') as HTMLButtonElement | null

    expect(accept).not.toBeNull()
    expect(close).not.toBeNull()
    expect(document.activeElement).toBe(accept)
    expect(close?.className).toContain('focus-visible:ring-2')
    expect(document.querySelector('[role="alertdialog"][aria-modal="true"]')).not.toBeNull()
  })
})
