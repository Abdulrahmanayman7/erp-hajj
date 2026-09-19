import { createApp, nextTick } from 'vue'
import { describe, expect, it } from 'vitest'

import AppListSelect, { type AppListSelectOption } from './AppListSelect.vue'

const options: AppListSelectOption[] = [
  { value: 1, label: 'أحمد محمد', hint: 'EMP-00001' },
  { value: 2, label: 'سارة علي', hint: 'EMP-00002' },
  { value: 3, label: 'خالد حسن', hint: 'EMP-00003', disabled: true },
]

function mountHost(initial?: {
  modelValue?: Array<string | number>
  remote?: boolean
  selectedOptions?: AppListSelectOption[]
}) {
  const el = document.createElement('div')
  document.body.appendChild(el)
  const app = createApp({
    components: { AppListSelect },
    data: () => ({
      value: initial?.modelValue ?? [],
      options,
      remote: initial?.remote ?? false,
      selectedOptions: initial?.selectedOptions ?? [],
    }),
    template: `
      <AppListSelect
        v-model="value"
        :options="options"
        :teleport="false"
        :remote="remote"
        :selected-options="selectedOptions"
      />
    `,
  })
  const vm = app.mount(el) as { value: Array<string | number> }
  return {
    el,
    vm,
    unmount: () => {
      app.unmount()
      el.remove()
    },
  }
}

describe('AppListSelect', () => {
  it('toggles multiple values without closing the menu', async () => {
    const { el, vm, unmount } = mountHost()

    el.querySelector<HTMLElement>('[role="combobox"]')?.click()
    await nextTick()
    expect(el.querySelector('[data-app-list-select-menu]')).toBeTruthy()

    const buttons = Array.from(el.querySelectorAll<HTMLButtonElement>('[role="option"] button'))
    buttons[0]?.click()
    await nextTick()
    expect(vm.value).toEqual([1])
    expect(el.querySelector('[data-app-list-select-menu]')).toBeTruthy()

    buttons[1]?.click()
    await nextTick()
    expect(vm.value).toEqual([1, 2])

    unmount()
  })

  it('filters local options by search', async () => {
    const { el, unmount } = mountHost()

    el.querySelector<HTMLElement>('[role="combobox"]')?.click()
    await nextTick()

    const input = el.querySelector<HTMLInputElement>('[data-list-select-search]')
    expect(input).toBeTruthy()
    input!.value = 'سارة'
    input!.dispatchEvent(new Event('input', { bubbles: true }))
    await nextTick()

    expect(el.textContent).toContain('سارة علي')
    expect(el.textContent).not.toContain('أحمد محمد')

    unmount()
  })

  it('clears the full selection from the trigger', async () => {
    const { el, vm, unmount } = mountHost({ modelValue: [1, 2] })

    await nextTick()
    el.querySelector<HTMLButtonElement>('button[aria-label="مسح الكل"]')?.click()
    await nextTick()
    expect(vm.value).toEqual([])

    unmount()
  })

  it('keeps selected labels when they are not in the current remote page', async () => {
    const { el, unmount } = mountHost({
      modelValue: [891],
      remote: true,
      selectedOptions: [{ value: 891, label: 'نورة أحمد', hint: 'EMP-00891' }],
    })

    await nextTick()
    expect(el.textContent).toContain('نورة أحمد')
    unmount()
  })
})
