import { describe, expect, it, vi } from 'vitest'
import { collectAccommodationPages } from './useAccommodationOptions'

describe('accommodation options', () => {
  it('loads all pages, including selections beyond the first 100 records', async () => {
    const fetchPage = vi.fn(async (page: number) => ({
      data: page === 1 ? [1, 2] : [3],
      meta: { current_page: page, per_page: 2, total: 3, last_page: 2 },
    }))
    expect(await collectAccommodationPages(fetchPage)).toEqual([1, 2, 3])
    expect(fetchPage.mock.calls.map(call => call[0])).toEqual([1, 2])
  })

  it('does not silently return partial options when a later page fails', async () => {
    await expect(collectAccommodationPages(async page => {
      if (page === 2) throw new Error('load failed')
      return { data: [1], meta: { current_page: 1, per_page: 1, total: 2, last_page: 2 } }
    })).rejects.toThrow('load failed')
  })
})
