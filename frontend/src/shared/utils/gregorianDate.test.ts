import { describe, expect, it } from 'vitest'

import {
  formatDatetimeLocalAr,
  formatIsoDateAr,
  isDatetimeLocal,
  isIsoDate,
  joinDatetimeLocal,
  monthGrid,
  parseDatetimeLocal,
  parseIsoDate,
  splitDatetimeLocal,
  toDatetimeLocal,
  toIsoDate,
  twelveYearBlock,
} from './gregorianDate'

describe('gregorianDate', () => {
  it('round-trips ISO dates without using the Islamic calendar', () => {
    expect(isIsoDate('2026-09-17')).toBe(true)
    expect(isIsoDate('17/09/2026')).toBe(false)
    expect(toIsoDate(new Date(2026, 8, 17))).toBe('2026-09-17')
    expect(parseIsoDate('2026-09-17')?.getDate()).toBe(17)
    expect(formatIsoDateAr('2026-09-17')).toContain('2026')
  })

  it('builds a Saturday-first Gregorian month grid', () => {
    const cells = monthGrid(2026, 8)
    expect(cells).toHaveLength(42)
    expect(cells[0]?.iso).toBe('2026-08-29')
    expect(new Date(2026, 7, 29).getDay()).toBe(6)
    expect(cells.filter((cell) => cell.inMonth).some((cell) => cell.iso === '2026-09-17')).toBe(true)
  })

  it('builds a 12-year block for year picking', () => {
    expect(twelveYearBlock(2026)).toEqual([2016, 2017, 2018, 2019, 2020, 2021, 2022, 2023, 2024, 2025, 2026, 2027])
  })

  it('round-trips datetime-local values', () => {
    expect(isDatetimeLocal('2026-09-18T19:14')).toBe(true)
    expect(isDatetimeLocal('2026-09-18')).toBe(false)
    expect(joinDatetimeLocal('2026-09-18', 7, 14)).toBe('2026-09-18T07:14')
    expect(splitDatetimeLocal('2026-09-18T19:14')).toEqual({
      date: '2026-09-18',
      hours: 19,
      minutes: 14,
    })
    expect(parseDatetimeLocal('2026-09-18T19:14')?.getHours()).toBe(19)
    expect(toDatetimeLocal(new Date(2026, 8, 18, 7, 14))).toBe('2026-09-18T07:14')
    expect(formatDatetimeLocalAr('2026-09-18T19:14')).toContain('2026')
  })
})
