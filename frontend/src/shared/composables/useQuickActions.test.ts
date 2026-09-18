import { describe, expect, it, vi } from 'vitest'
import { createApp, defineComponent, nextTick } from 'vue'

import { useQuickActions } from './useQuickActions'

vi.mock('@/shared/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: (permission: string) =>
      ['tasks.create', 'meetings.create', 'documents.create'].includes(permission),
  }),
}))

describe('useQuickActions', () => {
  it('returns only permitted create actions', async () => {
    let result!: ReturnType<typeof useQuickActions>
    const Host = defineComponent({
      setup() {
        result = useQuickActions()
        return () => null
      },
    })
    const el = document.createElement('div')
    const app = createApp(Host)
    app.mount(el)
    await nextTick()

    const keys = result.actions.value.map((action) => action.key)
    expect(keys).toEqual(['task', 'meeting', 'document'])
    expect(result.actions.value.every((a) => a.to.includes('?create=1'))).toBe(true)

    app.unmount()
    el.remove()
  })
})
