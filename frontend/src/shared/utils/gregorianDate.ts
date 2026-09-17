const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/

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

export function toIsoDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
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

export function formatIsoDateAr(value: string | null | undefined, fallback = ''): string {
  const date = parseIsoDate(value)
  if (!date) return fallback
  return new Intl.DateTimeFormat('ar-u-ca-gregory', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
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
