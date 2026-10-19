import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { createApp, defineComponent, h, nextTick } from 'vue'
import { afterEach, describe, expect, it, vi } from 'vitest'

import * as api from '../api/accommodationApi'
import { accommodationQueryKey } from '../queries/useAccommodationQueries'
import {
  useArriveAssignmentMutation,
  useCheckOutAssignmentMutation,
  useRelocateAssignmentMutation,
} from './useAccommodationMutations'

vi.mock('../api/accommodationApi', async () => {
  const actual = await vi.importActual<typeof import('../api/accommodationApi')>(
    '../api/accommodationApi',
  )
  return {
    ...actual,
    arriveAssignment: vi.fn(),
    checkOutAssignment: vi.fn(),
    relocateAssignment: vi.fn(),
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

const assignment = {
  id: 1,
  season_id: 1,
  season_pilgrim_id: 1,
  season_unit_id: 1,
  unit_id: 1,
  season_position_id: null,
  booking_status: 'confirmed' as const,
  operational_status: 'arrived' as const,
  starts_at: '2027-06-01T10:00:00Z',
  original_ends_at: '2027-06-05T18:00:00Z',
  effective_ends_at: '2027-06-05T18:00:00Z',
  actual_departed_at: null,
  checkout_source: null,
  relocated_from_assignment_id: null,
  capacity_reserved: true,
  created_at: null,
  updated_at: null,
}

describe('accommodation ops mutations', () => {
  afterEach(() => {
    vi.mocked(api.arriveAssignment).mockReset()
    vi.mocked(api.checkOutAssignment).mockReset()
    vi.mocked(api.relocateAssignment).mockReset()
  })

  it('invalidates after arrive success', async () => {
    vi.mocked(api.arriveAssignment).mockResolvedValue(assignment)
    const { api: exposed, invalidateSpy, unmount } = withQuerySetup(() => ({
      mutation: useArriveAssignmentMutation(),
    }))
    await exposed.mutation.mutateAsync({ id: 1 })
    await nextTick()
    expect(api.arriveAssignment).toHaveBeenCalledWith(1, undefined)
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: accommodationQueryKey })
    unmount()
  })

  it('does not invalidate when checkout fails', async () => {
    vi.mocked(api.checkOutAssignment).mockRejectedValue(
      Object.assign(new Error('before'), {
        status: 422,
        code: 'ACCOMMODATION_CHECKOUT_BEFORE_START',
      }),
    )
    const { api: exposed, invalidateSpy, unmount } = withQuerySetup(() => ({
      mutation: useCheckOutAssignmentMutation(),
    }))
    await expect(
      exposed.mutation.mutateAsync({ id: 1, payload: { occurred_at: '2027-06-01T09:00:00Z' } }),
    ).rejects.toBeTruthy()
    await nextTick()
    expect(invalidateSpy).not.toHaveBeenCalled()
    unmount()
  })

  it('invalidates after relocate success with destination payload', async () => {
    vi.mocked(api.relocateAssignment).mockResolvedValue({
      source: { ...assignment, booking_status: 'cancelled', capacity_reserved: false },
      destination: {
        ...assignment,
        id: 99,
        relocated_from_assignment_id: 1,
      },
    })
    const { api: exposed, invalidateSpy, unmount } = withQuerySetup(() => ({
      mutation: useRelocateAssignmentMutation(),
    }))
    await exposed.mutation.mutateAsync({
      id: 1,
      payload: { destination_season_unit_id: 5, check_in_after: true },
    })
    await nextTick()
    expect(api.relocateAssignment).toHaveBeenCalledWith(1, {
      destination_season_unit_id: 5,
      check_in_after: true,
    })
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: accommodationQueryKey })
    unmount()
  })
})
