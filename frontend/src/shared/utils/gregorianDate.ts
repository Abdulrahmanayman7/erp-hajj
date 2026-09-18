const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/
const DATETIME_LOCAL = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/

export const GREGORIAN_MONTHS_AR = [
  'يناير',
  'فبراير',
  'مارس',
  'أبريل',
  'مايو',
  'يونيو',
  'يوليو',
  'أغسطس',
  'سبتمبر',
  'أكتوبر',
  'نوفمبر',
  'ديسمبر',
] as const

export function twelveYearBlock(year: number): number[] {
  const start = Math.floor(year / 12) * 12
  return Array.from({ length: 12 }, (_, index) => start + index)
}

export function isIsoDate(value: string | null | undefined): boolean {
  return ISO_DATE.test(String(value ?? '').trim())
}

export function isDatetimeLocal(value: string | null | undefined): boolean {
  return DATETIME_LOCAL.test(String(value ?? '').trim())
}

export function toIsoDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function toDatetimeLocal(date: Date): string {
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${toIsoDate(date)}T${hours}:${minutes}`
}

export function parseIsoDate(value: string | null | undefined): Date | null {
  const raw = String(value ?? '').trim()
  if (!ISO_DATE.test(raw)) return null
  const [year, month, day] = raw.split('-').map(Number)
  const date = new Date(year, month - 1, day)
  if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
    return null
  }
  return date
}

export function parseDatetimeLocal(value: string | null | undefined): Date | null {
  const raw = String(value ?? '').trim()
  if (!DATETIME_LOCAL.test(raw)) return null
  const [datePart, timePart] = raw.split('T')
  const date = parseIsoDate(datePart)
  if (!date) return null
  const [hours, minutes] = timePart.split(':').map(Number)
  if (hours > 23 || minutes > 59) return null
  date.setHours(hours, minutes, 0, 0)
  return date
}

export function splitDatetimeLocal(value: string | null | undefined): {
  date: string
  hours: number
  minutes: number
} {
  if (isDatetimeLocal(value)) {
    const [date, time] = String(value).trim().split('T')
    const [hours, minutes] = time.split(':').map(Number)
    return { date, hours, minutes }
  }
  if (isIsoDate(value)) {
    return { date: String(value).trim(), hours: 0, minutes: 0 }
  }
  const now = new Date()
  return { date: '', hours: now.getHours(), minutes: now.getMinutes() }
}

export function joinDatetimeLocal(date: string, hours: number, minutes: number): string {
  if (!isIsoDate(date)) return ''
  const h = Math.min(23, Math.max(0, Math.trunc(hours)))
  const m = Math.min(59, Math.max(0, Math.trunc(minutes)))
  return `${date}T${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

export function formatIsoDateAr(value: string | null | undefined, fallback = ''): string {
  const date = parseIsoDate(value)
  if (!date) return fallback
  return new Intl.DateTimeFormat('ar-u-ca-gregory', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(date)
}

export function formatDatetimeLocalAr(value: string | null | undefined, fallback = ''): string {
  const date = parseDatetimeLocal(value)
  if (!date) return fallback
  return new Intl.DateTimeFormat('ar-u-ca-gregory', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date)
}

export type CalendarCell = {
  iso: string
  day: number
  inMonth: boolean
}

/** Saturday-first Gregorian month grid (6 weeks). */
export function monthGrid(year: number, monthIndex: number): CalendarCell[] {
  const startOffset = (new Date(year, monthIndex, 1).getDay() + 1) % 7
  const gridStart = new Date(year, monthIndex, 1 - startOffset)
  const cells: CalendarCell[] = []
  for (let i = 0; i < 42; i += 1) {
    const date = new Date(gridStart)
    date.setDate(gridStart.getDate() + i)
    cells.push({
      iso: toIsoDate(date),
      day: date.getDate(),
      inMonth: date.getMonth() === monthIndex,
    })
  }
  return cells
}
