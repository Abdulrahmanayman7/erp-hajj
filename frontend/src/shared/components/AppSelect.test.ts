import { createApp, nextTick } from 'vue'
import { describe, expect, it } from 'vitest'

import AppSelect, { type AppSelectOption } from './AppSelect.vue'

const options: AppSelectOption[] = [
  { value: 1, label: 'أحمد محمد', hint: 'EMP-00001' },
  { value: 2, label: 'سارة علي', hint: 'EMP-00002' },
]

function mountSelect(props: Record<string, unknown>) {
  const el = document.createElement('div')
  document.body.appendChild(el)
  const app = createApp(AppSelect, props)
  const vm = app.mount(el)
  return {
    el,
    vm,
    unmount: () => {
      app.unmount()
      el.remove()
    },
  }
}

describe('AppSelect remote mode', () => {
  it('hydrates a selected value that is not in the current page', async () => {
    const { el, unmount } = mountSelect({
      modelValue: 891,
      options,
      selectedOption: { value: 891, label: 'أحمد محمد', hint: 'EMP-00891' },
      remote: true,
      searchable: true,
    })

    await nextTick()
    expect(el.textContent).toContain('أحمد محمد')
    unmount()
  })

  it('emits search immediately and supports keyboard selection plus load more', async () => {
    const searches: string[] = []
    const { el, unmount } = mountSelect({
      modelValue: null,
      options,
      remote: true,
      searchable: true,
      hasMore: true,
      onSearch: (value: string) => searches.push(value),
      'onUpdate:modelValue': () => undefined,
      onLoadMore: () => searches.push('load-more'),
    })

    el.querySelector('button')?.click()
    await nextTick()

    const input = document.querySelector<HTMLInputElement>('[data-select-search]')
    expect(input).toBeTruthy()
    input!.value = 'أحمد'
    input!.dispatchEvent(new Event('input', { bubbles: true }))
    await nextTick()
    expect(searches).toContain('أحمد')

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }))
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }))
    await nextTick()

    document.querySelector<HTMLButtonElement>('[data-select-load-more]')?.click()
    expect(searches).toContain('load-more')

    unmount()
  })

  it('clears a nullable selection', async () => {
    let value: string | number | null = 1
    const { el, unmount } = mountSelect({
      modelValue: 1,
      options,
      clearable: true,
      'onUpdate:modelValue': (next: string | number | null) => {
        value = next
      },
    })

    await nextTick()
    el.querySelector<HTMLButtonElement>('button[aria-label="مسح الاختيار"]')?.click()
    expect(value).toBeNull()
    unmount()
  })

  it('shows retry when the remote lookup errors', async () => {
    let retried = false
    const { el, unmount } = mountSelect({
      modelValue: null,
      options: [],
      remote: true,
      searchable: true,
      error: true,
      teleport: false,
      onRetry: () => {
        retried = true
      },
    })

    el.querySelector('button')?.click()
    await nextTick()
    el.querySelector<HTMLButtonElement>('[data-select-retry]')?.click()
    expect(retried).toBe(true)
    unmount()
  })
})
