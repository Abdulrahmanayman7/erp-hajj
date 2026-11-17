import { createApp, h, nextTick, reactive } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, describe, expect, it, vi } from 'vitest'
import AccommodationFormDrawer from './AccommodationFormDrawer.vue'

const cleanups: Array<() => void> = []
afterEach(() => cleanups.splice(0).forEach((cleanup) => cleanup()))

function mountDrawer() {
  const state = reactive({ open: true, submitting: false, disabled: false })
  const close = vi.fn()
  const submit = vi.fn()
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp({
    render: () =>
      h(
        AccommodationFormDrawer,
        {
          ...state,
          onClose: close,
          onSubmit: submit,
        },
        {
          title: () => 'إضافة منشأة',
          default: () => h('input', { name: 'name' }),
        },
      ),
  })
  app.use(
    createI18n({
      legacy: false,
      locale: 'ar',
      messages: {
        ar: {
          accommodation: {
            experience: { eyebrow: 'الإسكان' },
            save: 'حفظ',
            cancel: 'إلغاء',
          },
          formDrawer: {
            close: 'إغلاق',
            save: 'حفظ',
            cancel: 'إلغاء',
            unsavedTitle: 'تجاهل التغييرات؟',
            unsavedMessage: '',
            discard: 'تجاهل التغييرات',
            keepEditing: 'متابعة التعديل',
          },
          common: { loading: 'جار التحميل' },
        },
      },
    }),
  )
  app.mount(host)
  cleanups.push(() => {
    app.unmount()
    host.remove()
  })
  return { state, close, submit }
}

describe('AccommodationFormDrawer', () => {
  it('shares the standard drawer layout and emits a form submission', async () => {
    const { submit } = mountDrawer()
    await nextTick()
    const dialog = document.querySelector('[role="dialog"]')!
    expect(dialog.classList.contains('max-w-[560px]')).toBe(true)
    expect(dialog.textContent).toContain('إضافة منشأة')
    dialog
      .querySelector('form')!
      .dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    expect(submit).toHaveBeenCalledOnce()
  })

  it('blocks repeated submissions and closing while saving', async () => {
    const { state, close, submit } = mountDrawer()
    state.submitting = true
    await nextTick()
    const dialog = document.querySelector('[role="dialog"]')!
    const buttons = Array.from(dialog.querySelectorAll('button'))
    expect(buttons.every((button) => button.disabled)).toBe(true)
    dialog
      .querySelector('form')!
      .dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    expect(submit).not.toHaveBeenCalled()
    expect(close).not.toHaveBeenCalled()
  })

  it('supports cancellation and removes the dialog when closed', async () => {
    const { state, close } = mountDrawer()
    await nextTick()
    document.querySelector<HTMLButtonElement>('[data-test="app-form-drawer-close"]')!.click()
    expect(close).toHaveBeenCalledOnce()
    state.open = false
    await nextTick()
    // Leave transition (~220ms) keeps the panel mounted briefly.
    await new Promise((resolve) => setTimeout(resolve, 260))
    expect(document.querySelector('[role="dialog"]')).toBeNull()
  })
})
