import { afterEach, describe, expect, it, vi } from 'vitest'

import { apiGet, resetCsrfBootstrap } from '@/shared/api/http'

import {
  arriveAssignment,
  cancelConfirmedAssignment,
  checkInAssignment,
  checkOutAssignment,
  confirmAssignment,
  confirmFamilyAssignments,
  listFacilities,
  relocateAssignment,
} from './accommodationApi'

vi.mock('../stores/seasonContextStore', () => ({
  useAccommodationSeasonStore: () => ({
    seasonHeaders: () => ({ 'X-Season-Id': '42' }),
  }),
}))

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

describe('accommodationApi season header', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    resetCsrfBootstrap()
  })

  it('sends X-Season-Id on facility list requests', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse({
        success: true,
        message: '',
        data: [],
        meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
      }),
    )
    vi.stubGlobal('fetch', fetchMock)

    await listFacilities(1, 15)

    expect(fetchMock).toHaveBeenCalled()
    const init = fetchMock.mock.calls[0]?.[1] as RequestInit
    const headers = init.headers as Record<string, string>
    expect(headers['X-Season-Id']).toBe('42')
    expect(String(fetchMock.mock.calls[0]?.[0])).toContain('/api/v1/accommodation/facilities')
  })
})

describe('accommodationApi confirmation', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    resetCsrfBootstrap()
  })

  function stubFetch(handler: (url: string) => Response): ReturnType<typeof vi.fn> {
    const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input)
      if (url.includes('/sanctum/csrf-cookie')) {
        return new Response(null, { status: 204 })
      }
      return handler(url)
    })
    vi.stubGlobal('fetch', fetchMock)
    return fetchMock
  }

  it('posts individual confirm without client booking_status', async () => {
    const fetchMock = stubFetch(() =>
      jsonResponse({
        success: true,
        message: '',
        data: {
          id: 11,
          booking_status: 'confirmed',
          capacity_reserved: true,
        },
      }),
    )

    const row = await confirmAssignment(11)
    expect(row.booking_status).toBe('confirmed')
    const confirmCall = fetchMock.mock.calls.find(([url]) =>
      String(url).includes('/api/v1/accommodation/assignments/11/confirm'),
    )
    expect(confirmCall).toBeTruthy()
    const init = confirmCall?.[1] as RequestInit
    expect(init.method).toBe('POST')
    expect(JSON.parse(String(init.body))).toEqual({})
    expect((init.headers as Record<string, string>)['X-Season-Id']).toBe('42')
  })

  it('posts atomic family confirm with frozen payload keys only', async () => {
    const fetchMock = stubFetch(() =>
      jsonResponse({
        success: true,
        message: '',
        data: [
          { id: 1, booking_status: 'confirmed', capacity_reserved: true },
          { id: 2, booking_status: 'confirmed', capacity_reserved: true },
        ],
      }),
    )

    const rows = await confirmFamilyAssignments({
      season_family_group_id: 5,
      assignment_ids: [1, 2],
    })
    expect(rows).toHaveLength(2)
    const familyCall = fetchMock.mock.calls.find(([url]) =>
      String(url).includes('/api/v1/accommodation/assignments/confirm-family'),
    )
    expect(familyCall).toBeTruthy()
    const body = JSON.parse(String((familyCall?.[1] as RequestInit).body))
    expect(body).toEqual({
      season_family_group_id: 5,
      assignment_ids: [1, 2],
    })
    expect(body.booking_status).toBeUndefined()
  })

  it('surfaces D7 error codes without treating failure as success', async () => {
    stubFetch(() =>
      jsonResponse(
        {
          success: false,
          message: 'overlap',
          code: 'ACCOMMODATION_PILGRIM_OVERLAP',
        },
        422,
      ),
    )

    await expect(confirmAssignment(3)).rejects.toMatchObject({
      status: 422,
      code: 'ACCOMMODATION_PILGRIM_OVERLAP',
    })
  })
})

describe('accommodationApi operations', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    resetCsrfBootstrap()
  })

  function stubFetch(handler: (url: string, init?: RequestInit) => Response): ReturnType<typeof vi.fn> {
    const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input)
      if (url.includes('/sanctum/csrf-cookie')) {
        return new Response(null, { status: 204 })
      }
      return handler(url, init)
    })
    vi.stubGlobal('fetch', fetchMock)
    return fetchMock
  }

  it('posts arrive and check-in with season header and empty body by default', async () => {
    const fetchMock = stubFetch(() =>
      jsonResponse({
        success: true,
        message: '',
        data: {
          id: 9,
          booking_status: 'confirmed',
          operational_status: 'arrived',
          capacity_reserved: true,
        },
      }),
    )

    await arriveAssignment(9)
    await checkInAssignment(9)

    const arriveCall = fetchMock.mock.calls.find(([url]) =>
      String(url).includes('/api/v1/accommodation/assignments/9/arrive'),
    )
    const checkInCall = fetchMock.mock.calls.find(([url]) =>
      String(url).includes('/api/v1/accommodation/assignments/9/check-in'),
    )
    expect(arriveCall).toBeTruthy()
    expect(checkInCall).toBeTruthy()
    expect(JSON.parse(String((arriveCall?.[1] as RequestInit).body))).toEqual({})
    expect(JSON.parse(String((checkInCall?.[1] as RequestInit).body))).toEqual({})
    expect(((arriveCall?.[1] as RequestInit).headers as Record<string, string>)['X-Season-Id']).toBe(
      '42',
    )
  })

  it('posts checkout occurred_at without inventing booking_status', async () => {
    const fetchMock = stubFetch(() =>
      jsonResponse({
        success: true,
        message: '',
        data: {
          id: 4,
          booking_status: 'completed',
          operational_status: 'checked_out',
          actual_departed_at: '2027-06-02T12:00:00Z',
          capacity_reserved: false,
        },
      }),
    )

    await checkOutAssignment(4, { occurred_at: '2027-06-02T12:00:00Z' })
    const call = fetchMock.mock.calls.find(([url]) =>
      String(url).includes('/api/v1/accommodation/assignments/4/check-out'),
    )
    const body = JSON.parse(String((call?.[1] as RequestInit).body))
    expect(body).toEqual({ occurred_at: '2027-06-02T12:00:00Z' })
    expect(body.booking_status).toBeUndefined()
  })

  it('posts relocate destination contract and surfaces relocate interval errors', async () => {
    const fetchMock = stubFetch((url) => {
      if (url.includes('/relocate')) {
        return jsonResponse(
          {
            success: false,
            message: 'interval',
            code: 'ACCOMMODATION_RELOCATE_INTERVAL_INVALID',
          },
          422,
        )
      }
      return jsonResponse({ success: true, message: '', data: {} })
    })

    await expect(
      relocateAssignment(8, {
        destination_season_unit_id: 12,
        destination_season_position_id: 3,
        check_in_after: true,
        occurred_at: '2027-06-03T12:00:00Z',
      }),
    ).rejects.toMatchObject({
      status: 422,
      code: 'ACCOMMODATION_RELOCATE_INTERVAL_INVALID',
    })

    const call = fetchMock.mock.calls.find(([url]) =>
      String(url).includes('/api/v1/accommodation/assignments/8/relocate'),
    )
    expect(JSON.parse(String((call?.[1] as RequestInit).body))).toEqual({
      destination_season_unit_id: 12,
      destination_season_position_id: 3,
      occurred_at: '2027-06-03T12:00:00Z',
      check_in_after: true,
    })
  })

  it('posts staff cancel without draft semantics', async () => {
    const fetchMock = stubFetch(() =>
      jsonResponse({
        success: true,
        message: '',
        data: { id: 2, booking_status: 'cancelled', capacity_reserved: false },
      }),
    )

    await cancelConfirmedAssignment(2, { reason: 'تغيير خطة' })
    const call = fetchMock.mock.calls.find(([url]) =>
      String(url).includes('/api/v1/accommodation/assignments/2/cancel'),
    )
    expect(JSON.parse(String((call?.[1] as RequestInit).body))).toEqual({ reason: 'تغيير خطة' })
  })

  it('surfaces checkout-before-start without treating as cancel', async () => {
    stubFetch(() =>
      jsonResponse(
        {
          success: false,
          message: 'before start',
          code: 'ACCOMMODATION_CHECKOUT_BEFORE_START',
        },
        422,
      ),
    )

    await expect(checkOutAssignment(1, { occurred_at: '2027-06-01T09:00:00Z' })).rejects.toMatchObject({
      code: 'ACCOMMODATION_CHECKOUT_BEFORE_START',
    })
  })
})

describe('apiGet headers option', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    resetCsrfBootstrap()
  })

  it('forwards custom headers without inventing tenant identity', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ success: true, message: '', data: { ok: true } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )
    vi.stubGlobal('fetch', fetchMock)

    await apiGet('/api/v1/season-context', { headers: { 'X-Season-Id': '7' } })

    const headers = (fetchMock.mock.calls[0]?.[1] as RequestInit).headers as Record<string, string>
    expect(headers['X-Season-Id']).toBe('7')
    expect(headers.tenant_id).toBeUndefined()
  })
})
