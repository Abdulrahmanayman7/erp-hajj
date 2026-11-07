import { createApp, defineComponent, h, nextTick, ref } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, describe, expect, it } from 'vitest'

import { seasonalTemplatesAr } from '@/locales/seasonalTemplatesAr'

import EntityKindCheckboxes from './EntityKindCheckboxes.vue'

let root: HTMLElement | null = null

function mount(options: { available?: string[]; initial?: string[] } = {}) {
  const model = ref<string[]>(options.initial ?? [])
  const i18n = createI18n({
    legacy: false,
    locale: 'ar',
    messages: { ar: { seasonalTemplates: seasonalTemplatesAr.seasonalTemplates } },
  })
  const Host = defineComponent({
    render: () =>
      h(EntityKindCheckboxes, {
        modelValue: model.value,
        available: options.available,
        'onUpdate:modelValue': (value: string[]) => {
          model.value = value
        },
      }),
  })
  root = document.createElement('div')
  document.body.appendChild(root)
  createApp(Host).use(i18n).mount(root)
  return { model, root }
}

afterEach(() => {
  root?.remove()
  root = null
})

describe('EntityKindCheckboxes', () => {
  it('renders only the five approved kinds', async () => {
    const { root: el } = mount()
    await nextTick()
    const boxes = el.querySelectorAll('input[type="checkbox"]')
    expect(boxes).toHaveLength(5)
  })

  it('never renders an unapproved kind even if offered', async () => {
    const { root: el } = mount({ available: ['season_programs', 'season_pilgrims', 'finance_ledger'] })
    await nextTick()
    expect(el.querySelectorAll('input[type="checkbox"]')).toHaveLength(1)
    expect(el.querySelector('[data-testid="entity-kind-season_programs"]')).not.toBeNull()
    expect(el.querySelector('[data-testid="entity-kind-season_pilgrims"]')).toBeNull()
  })

  it('emits approved kinds in canonical order on toggle', async () => {
    const { root: el, model } = mount({ initial: ['transport_entitlements'] })
    await nextTick()
    const box = el.querySelector('[data-testid="entity-kind-season_programs"]') as HTMLInputElement
    box.checked = true
    box.dispatchEvent(new Event('change'))
    await nextTick()
    expect(model.value).toEqual(['season_programs', 'transport_entitlements'])
  })
})
