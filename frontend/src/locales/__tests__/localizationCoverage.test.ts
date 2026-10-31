import { describe, expect, it } from 'vitest'
import { readFileSync, readdirSync } from 'node:fs'
import { resolve, join } from 'node:path'
import { ar } from '../ar'
import { accommodationAr } from '../accommodationAr'
import { seasonPilgrimsAr } from '../seasonPilgrimsAr'
import { transportationAr } from '../transportationAr'
import { auditEventLabel, auditEntityDisplay, formatAuditValue } from '@/modules/audit/utils/auditDisplay'
const messages = {
  ...ar,
  nav: { ...ar.nav, ...accommodationAr.nav, ...seasonPilgrimsAr.nav, ...transportationAr.nav },
  accommodation: accommodationAr.accommodation,
  pilgrims: seasonPilgrimsAr.pilgrims,
  seasons: seasonPilgrimsAr.seasons,
  seasonOps: seasonPilgrimsAr.seasonOps,
  transportation: transportationAr.transportation,
}
function files(directory: string): string[] { return readdirSync(directory, { withFileTypes: true }).flatMap(entry => entry.isDirectory() ? files(join(directory, entry.name)) : [join(directory, entry.name)]) }
const lookup = (key: string) => key.split('.').reduce<unknown>((value, part) => (value as Record<string, unknown> | undefined)?.[part], messages)
describe('Arabic display coverage', () => {
  it('covers every literal translation key in application source', () => {
    const missing: string[] = []
    for (const file of files(resolve('src')).filter(path => /\.(vue|ts)$/.test(path) && !path.endsWith('.test.ts'))) {
      for (const match of readFileSync(file, 'utf8').matchAll(/\bt\(\s*['"]([^'"]+)['"]\s*[,)]/g)) if (lookup(match[1]!) === undefined) missing.push(file + ': ' + match[1])
    }
    expect(missing).toEqual([])
  })
  it('covers all canonical backend audit events without exposing their codes', () => {
    const sources = ['Authorization', 'Auth'].map(module => readFileSync(resolve('../backend/app/Core/' + module + '/Events/' + (module === 'Auth' ? 'Auth' : 'Authorization') + 'SecurityEvent.php'), 'utf8')).join('\n')
    for (const match of sources.matchAll(/public const [A-Z_]+ = '([A-Z_]+)'/g)) {
      const code = match[1]!
      expect(ar.audit.events).toHaveProperty(code)
      expect(auditEventLabel(code)).toMatch(/[\u0600-\u06ff]/)
      expect(auditEventLabel(code)).not.toBe(code)
    }
  })
  it('covers dynamic accommodation enum keys', () => {
    const sets = { 'season.status': ['draft', 'active', 'closed'], bookingStatus: ['draft', 'confirmed', 'completed', 'cancelled'], audience: ['male', 'female', 'family'], allocationMode: ['capacity', 'position'], kind: ['hotel', 'camp'], unitKind: ['room', 'tent'] }
    for (const [prefix, values] of Object.entries(sets)) for (const value of values) expect(lookup('accommodation.' + prefix + '.' + value)).toBeTypeOf('string')
  })
  it('formats entity types, booleans, statuses and dates for the Arabic reader', () => {
    expect(auditEntityDisplay({ entity_type: 'accommodation_supervisor_scope', entity_label: null, entity_number: null })).toBe('نطاق الإشراف على الإسكان')
    expect(formatAuditValue(true)).toBe('نعم')
    expect(formatAuditValue('draft', 'booking_status')).toBe('مسودة')
    expect(formatAuditValue('2027-06-01T20:00:00Z', 'starts_at', 'Asia/Riyadh')).toContain('2027')
  })
})
