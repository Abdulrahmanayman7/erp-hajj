import { createApp, h, nextTick, ref } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { __resetBodyScrollLockForTests } from '@/shared/composables/useBodyScrollLock'

const confirmMock = vi.fn()

vi.mock('@/shared/composables/useConfirm', () => ({
  useConfirm: () => ({
    confirm: confirmMock,
    state: { value: { open: false } },
    accept: vi.fn(),
    cancel: vi.fn(),
  }),
}))

import AppFormDrawer from './AppFormDrawer.vue'

const messages = {
  ar: {
    common: { loading: 'جارٍ التحميل…' },
    formDrawer: {
      close: 'إغلاق',
      cancel: 'إلغاء',
      save: 'حفظ',
      unsavedTitle: 'تجاهل التغييرات؟',
      unsavedMessage: 'لديك بيانات لم يتم حفظها.',
      keepEditing: 'متابعة التعديل',
      discard: 'تجاهل التغييرات',
    },
  },
}

describe('AppFormDrawer', () => {
  const apps: Array<ReturnType<typeof createApp>> = []

  beforeEach(() => {
    __resetBodyScrollLockForTests()
    confirmMock.mockReset()
    confirmMock.mockResolvedValue(false)
    document.body.innerHTML = ''
  })

  afterEach(() => {
    apps.splice(0).forEach((app) => app.unmount())
    document.body.innerHTML = ''
    __resetBodyScrollLockForTests()
  })

  function mountDrawer(options: {
    open?: boolean
    dirty?: boolean
    submitting?: boolean
    loading?: boolean
  } = {}) {
    const open = ref(options.open ?? true)
    const onClose = vi.fn()
    const onSubmit = vi.fn()
    const host = document.createElement('div')
    document.body.appendChild(host)
    const i18n = createI18n({ legacy: false, locale: 'ar', messages })

    const app = createApp({
      setup() {
        return () =>
          h(
            AppFormDrawer,
            {
              open: open.value,
              title: 'نموذج تجريبي',
              description: 'وصف',
              dirty: options.dirty ?? false,
              submitting: options.submitting ?? false,
              loading: options.loading ?? false,
              'onUpdate:open': (value: boolean) => {
                open.value = value
              },
              onClose,
              onSubmit,
            },
            () => h('label', ['الاسم', h('input', { 'data-test': 'field-name' })]),
          )
      },
    })
    app.use(i18n)
    app.mount(host)
    apps.push(app)
    return { open, onClose, onSubmit, rerender: () => app._instance?.update() }
  }

  it('renders accessible dialog chrome when open', async () => {
    mountDrawer()
    await nextTick()
    const panel = document.querySelector('[data-test="app-form-drawer-panel"]')
    expect(panel).not.toBeNull()
    expect(panel?.getAttribute('role')).toBe('dialog')
    expect(panel?.getAttribute('aria-modal')).toBe('true')
  })

  it('emits submit from the default footer', async () => {
    const { onSubmit } = mountDrawer()
    await nextTick()
    ;(document.querySelector('[data-test="app-form-drawer-submit"]') as HTMLButtonElement).click()
    await nextTick()
    expect(onSubmit).toHaveBeenCalledTimes(1)
  })

  it('closes on backdrop when not dirty', async () => {
    const { open, onClose } = mountDrawer({ dirty: false })
    await nextTick()
    ;(document.querySelector('[data-test="app-form-drawer-backdrop"]') as HTMLElement).click()
    await nextTick()
    expect(open.value).toBe(false)
    expect(onClose).toHaveBeenCalled()
    expect(confirmMock).not.toHaveBeenCalled()
  })

  it('asks for confirmation before discarding dirty state', async () => {
    confirmMock.mockResolvedValueOnce(true)
    const { open, onClose } = mountDrawer({ dirty: true })
    await nextTick()
    ;(document.querySelector('[data-test="app-form-drawer-close"]') as HTMLButtonElement).click()
    await nextTick()
    await Promise.resolve()
    await Promise.resolve()
    expect(confirmMock).toHaveBeenCalled()
    expect(open.value).toBe(false)
    expect(onClose).toHaveBeenCalled()
  })

  it('keeps the drawer open when unsaved discard is cancelled', async () => {
    confirmMock.mockResolvedValueOnce(false)
    const { open } = mountDrawer({ dirty: true })
    await nextTick()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    await Promise.resolve()
    expect(open.value).toBe(true)
  })

  it('shows loading state instead of the form body', async () => {
    mountDrawer({ loading: true })
    await nextTick()
    expect(document.querySelector('[data-test="app-form-drawer-loading"]')).not.toBeNull()
    expect(document.querySelector('[data-test="field-name"]')).toBeNull()
  })
})
