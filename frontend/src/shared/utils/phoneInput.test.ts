import { describe, expect, it } from 'vitest'

import {
  countryByIso,
  digitsOnly,
  filterPhoneCountries,
  flagEmoji,
  formatE164,
  parsePhone,
  toAsciiDigits,
} from './phoneInput'
import { sanitizeNumberInput } from './numberInput'

describe('phoneInput', () => {
  it('parses E.164 Saudi numbers and local trunk prefix', () => {
    expect(parsePhone('+966501234567')).toEqual({ iso: 'SA', national: '501234567' })
    expect(parsePhone('0501234567')).toEqual({ iso: 'SA', national: '501234567' })
    expect(parsePhone('00966501234567')).toEqual({ iso: 'SA', national: '501234567' })
  })

  it('parses other GCC dial codes', () => {
    expect(parsePhone('+971501234567').iso).toBe('AE')
    expect(parsePhone('+201012345678').iso).toBe('EG')
  })

  it('formats a single stored string without employee_id-style extra fields', () => {
    expect(formatE164('SA', '0501234567')).toBe('+966501234567')
    expect(formatE164('SA', '')).toBe('')
    expect(formatE164('EG', '1012345678')).toBe('+201012345678')
  })

  it('normalizes Arabic digits and flags', () => {
    expect(toAsciiDigits('٥٠١')).toBe('501')
    expect(digitsOnly('+966-50 123')).toBe('96650123')
    expect(flagEmoji('SA')).toBe('🇸🇦')
    expect(countryByIso('KW').dial).toBe('965')
  })

  it('filters countries by name or dial', () => {
    expect(filterPhoneCountries('سعود').map((row) => row.iso)).toContain('SA')
    expect(filterPhoneCountries('971').map((row) => row.iso)).toContain('AE')
  })
})

describe('sanitizeNumberInput', () => {
  it('strips spinner-style junk and keeps a single decimal', () => {
    expect(sanitizeNumberInput('12.3.4')).toBe('12.34')
    expect(sanitizeNumberInput('١٢.٥')).toBe('12.5')
    expect(sanitizeNumberInput('1e4', { integer: true })).toBe('14')
    expect(sanitizeNumberInput('-3', { unsigned: true })).toBe('3')
  })
})
