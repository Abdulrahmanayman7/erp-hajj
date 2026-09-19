export interface PhoneCountry {
  iso: string
  name: string
  dial: string
}

export const DEFAULT_PHONE_COUNTRY = 'SA'

/** GCC first, then common regional/international codes. Stored value remains a single E.164 string. */
export const PHONE_COUNTRIES: PhoneCountry[] = [
  { iso: 'SA', name: 'السعودية', dial: '966' },
  { iso: 'AE', name: 'الإمارات', dial: '971' },
  { iso: 'KW', name: 'الكويت', dial: '965' },
  { iso: 'QA', name: 'قطر', dial: '974' },
  { iso: 'BH', name: 'البحرين', dial: '973' },
  { iso: 'OM', name: 'عُمان', dial: '968' },
  { iso: 'EG', name: 'مصر', dial: '20' },
  { iso: 'JO', name: 'الأردن', dial: '962' },
  { iso: 'PS', name: 'فلسطين', dial: '970' },
  { iso: 'LB', name: 'لبنان', dial: '961' },
  { iso: 'IQ', name: 'العراق', dial: '964' },
  { iso: 'YE', name: 'اليمن', dial: '967' },
  { iso: 'SY', name: 'سوريا', dial: '963' },
  { iso: 'SD', name: 'السودان', dial: '249' },
  { iso: 'MA', name: 'المغرب', dial: '212' },
  { iso: 'DZ', name: 'الجزائر', dial: '213' },
  { iso: 'TN', name: 'تونس', dial: '216' },
  { iso: 'TR', name: 'تركيا', dial: '90' },
  { iso: 'IN', name: 'الهند', dial: '91' },
  { iso: 'PK', name: 'باكستان', dial: '92' },
  { iso: 'GB', name: 'بريطانيا', dial: '44' },
  { iso: 'FR', name: 'فرنسا', dial: '33' },
  { iso: 'US', name: 'الولايات المتحدة', dial: '1' },
]

const DIAL_BY_LENGTH = [...PHONE_COUNTRIES].sort((a, b) => b.dial.length - a.dial.length)

const ARABIC_DIGITS = '٠١٢٣٤٥٦٧٨٩'
const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹'

export function toAsciiDigits(value: string): string {
  return value.replace(/[٠-٩۰-۹]/g, (digit) => {
    const arabic = ARABIC_DIGITS.indexOf(digit)
    if (arabic >= 0) return String(arabic)
    const persian = PERSIAN_DIGITS.indexOf(digit)
    return persian >= 0 ? String(persian) : digit
  })
}

export function digitsOnly(value: string): string {
  return toAsciiDigits(value).replace(/\D/g, '')
}

export function flagEmoji(iso: string): string {
  return iso
    .toUpperCase()
    .replace(/[^A-Z]/g, '')
    .slice(0, 2)
    .split('')
    .map((char) => String.fromCodePoint(127397 + char.charCodeAt(0)))
    .join('')
}

export function countryByIso(iso: string): PhoneCountry {
  return PHONE_COUNTRIES.find((row) => row.iso === iso) ?? PHONE_COUNTRIES[0]!
}

export function formatE164(iso: string, national: string): string {
  const country = countryByIso(iso)
  const local = stripTrunkZero(digitsOnly(national)).slice(0, 15)
  if (!local) return ''
  return `+${country.dial}${local}`
}

export function parsePhone(
  value: string | null | undefined,
  fallbackIso = DEFAULT_PHONE_COUNTRY,
): { iso: string; national: string } {
  const fallback = countryByIso(fallbackIso)
  const raw = String(value ?? '').trim()
  if (!raw) return { iso: fallback.iso, national: '' }

  let normalized = toAsciiDigits(raw).replace(/[\s\-().]/g, '')
  if (normalized.startsWith('00')) normalized = `+${normalized.slice(2)}`

  if (normalized.startsWith('+')) {
    const rest = digitsOnly(normalized.slice(1))
    const matched = DIAL_BY_LENGTH.find((row) => rest.startsWith(row.dial))
    if (matched) {
      return { iso: matched.iso, national: stripTrunkZero(rest.slice(matched.dial.length)) }
    }
    return { iso: fallback.iso, national: stripTrunkZero(rest) }
  }

  const digits = digitsOnly(normalized)
  if (!digits) return { iso: fallback.iso, national: '' }

  if (digits.startsWith('0')) {
    return { iso: fallback.iso, national: stripTrunkZero(digits) }
  }

  const matched = DIAL_BY_LENGTH.find((row) => digits.startsWith(row.dial) && digits.length > row.dial.length + 4)
  if (matched) {
    return { iso: matched.iso, national: stripTrunkZero(digits.slice(matched.dial.length)) }
  }

  return { iso: fallback.iso, national: stripTrunkZero(digits) }
}

export function filterPhoneCountries(query: string): PhoneCountry[] {
  const needle = query.trim().toLowerCase()
  if (!needle) return PHONE_COUNTRIES
  const digits = digitsOnly(needle)
  return PHONE_COUNTRIES.filter((row) => {
    if (row.name.includes(query.trim()) || row.iso.toLowerCase().includes(needle)) return true
    if (digits && row.dial.includes(digits)) return true
    return `+${row.dial}`.includes(needle)
  })
}

function stripTrunkZero(national: string): string {
  return national.replace(/^0+/, '')
}
