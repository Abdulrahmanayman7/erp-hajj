import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { createApp, defineComponent, h, nextTick } from 'vue'
import { afterEach, describe, expect, it, vi } from 'vitest'

import * as api from '../api/accommodationApi'
import { accommodationQueryKey } from '../queries/useAccommodationQueries'
import {
  useConfirmAssignmentMutation,
  useConfirmFamilyAssignmentsMutation,
} from './useAccommodationMutations'

vi.mock('../api/accommodationApi', async () => {
  const actual = await vi.importActual<typeof import('../api/accommodationApi')>(
    '../api/accommodationApi',
  )
  return {
    ...actual,
    confirmAssignment: vi.fn(),
    confirmFamilyAssignments: vi.fn(),
  }
})

function withQuerySetup<T extends Record<string, unknown>>(setup: () => T): {
  api: T
  invalidateSpy: ReturnType<typeof vi.spyOn>
  unmount: () => void
} {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  const invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries')
  let exposed!: T
  const Comp = defineComponent({
    setup() {
      exposed = setup()
      return () => h('div')
    },
  })
  const app = createApp(Comp)
  app.use(VueQueryPlugin, { queryClient })
  const el = document.createElement('div')
  app.mount(el)
  return {
    api: exposed,
    invalidateSpy,
    unmount: () => app.unmount(),
  }
}

describe('accommodation confirm mutations', () => {
  afterEach(() => {
    vi.mocked(api.confirmAssignment).mockReset()
    vi.mocked(api.confirmFamilyAssignments).mockReset()
  })

  it('invalidates accommodation queries after individual confirm success', async () => {
    vi.mocked(api.confirmAssignment).mockResolvedValue({
      id: 1,
      season_id: 1,
      season_pilgrim_id: 1,
      season_unit_id: 1,
      unit_id: 1,
      season_position_id: null,
      booking_status: 'confirmed',
      operational_status: null,
      starts_at: '2027-06-01T10:00:00Z',
      original_ends_at: '2027-06-01T12:00:00Z',
      effective_ends_at: '2027-06-01T12:00:00Z',
      actual_departed_at: null,
      checkout_source: null,
      relocated_from_assignment_id: null,
      capacity_reserved: true,
      created_at: null,
      updated_at: null,
    })

    const { api: exposed, invalidateSpy, unmount } = withQuerySetup(() => ({
      mutation: useConfirmAssignmentMutation(),
    }))

    await exposed.mutation.mutateAsync(1)
    await nextTick()

    expect(api.confirmAssignment).toHaveBeenCalledTimes(1)
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: accommodationQueryKey })
    unmount()
  })

  it('does not invalidate on family confirm failure (no partial success)', async () => {
    vi.mocked(api.confirmFamilyAssignments).mockRejectedValue(
      Object.assign(new Error('conflict'), { status: 422, code: 'ACCOMMODATION_CAPACITY_CONFLICT' }),
    )

    const { api: exposed, invalidateSpy, unmount } = withQuerySetup(() => ({
      mutation: useConfirmFamilyAssignmentsMutation(),
    }))

    await expect(
      exposed.mutation.mutateAsync({ season_family_group_id: 1, assignment_ids: [1, 2] }),
    ).rejects.toBeTruthy()
    await nextTick()

    expect(invalidateSpy).not.toHaveBeenCalled()
    expect(vi.mocked(api.confirmFamilyAssignments).mock.calls[0]?.[0]).toEqual({
      season_family_group_id: 1,
      assignment_ids: [1, 2],
    })
    unmount()
  })
})
