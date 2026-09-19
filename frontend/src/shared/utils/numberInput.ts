const ARABIC_DIGITS = '٠١٢٣٤٥٦٧٨٩'
const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹'

export function sanitizeNumberInput(
  raw: string,
  options: { integer?: boolean; unsigned?: boolean } = {},
): string {
  let value = raw.replace(/[٠-٩۰-۹]/g, (digit) => {
    const arabic = ARABIC_DIGITS.indexOf(digit)
    if (arabic >= 0) return String(arabic)
    const persian = PERSIAN_DIGITS.indexOf(digit)
    return persian >= 0 ? String(persian) : digit
  })

  value = value.replace(/,/g, '.')
  const negative = !options.unsigned && value.includes('-')
  value = value.replace(/[^\d.]/g, '')

  if (options.integer) {
    value = value.replace(/\./g, '')
  } else {
    const dot = value.indexOf('.')
    if (dot !== -1) {
      value = `${value.slice(0, dot + 1)}${value.slice(dot + 1).replace(/\./g, '')}`
    }
  }

  if (negative && value !== '') return `-${value}`
  return value
}
