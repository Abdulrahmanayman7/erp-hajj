import type {
  ContractDefinitionRowForm,
  ContractDefinitionSnapshotRow,
} from '../types/seasonTemplates'

/** Same bound as the server-side snapshot normalizer (UX mirror only; the server re-validates). */
export const MAX_CONTRACT_ROWS = 500

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/

export function emptyContractRow(): ContractDefinitionRowForm {
  return {
    code: '',
    title: '',
    contract_category_id: '',
    counterparty_kind: 'organization',
    currency: '',
    start_date: '',
    end_date: '',
    notes: '',
  }
}

export function isValidIsoDate(value: string): boolean {
  if (!DATE_RE.test(value)) return false
  const date = new Date(`${value}T00:00:00Z`)
  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value
}

export interface ContractRowsErrors {
  anchor?: string
  /** One entry per row (same index); field → validation code (mapped to Arabic by the component). */
  rows: Array<Record<string, string>>
  /** Row-count level error (too many rows). */
  list?: string
  hasErrors: boolean
}

/** True when the editor holds at least one row (an empty editor sends no snapshot). */
export function hasContractRows(rows: readonly ContractDefinitionRowForm[]): boolean {
  return rows.length > 0
}

export function validateContractRows(
  rows: readonly ContractDefinitionRowForm[],
  anchor: string,
): ContractRowsErrors {
  const result: ContractRowsErrors = { rows: rows.map(() => ({})), hasErrors: false }
  if (rows.length === 0) return result

  if (anchor.trim() === '') result.anchor = 'required'
  else if (!isValidIsoDate(anchor.trim())) result.anchor = 'invalid_date'

  if (rows.length > MAX_CONTRACT_ROWS) result.list = 'rows_too_many'

  const seen = new Map<string, number>()
  rows.forEach((row, index) => {
    const errors = result.rows[index]
    const title = row.title.trim()
    if (!title) errors.title = 'required'
    else if (title.length > 255) errors.title = 'too_long'

    const code = row.code.trim()
    if (code.length > 255) errors.code = 'too_long'
    else if (code !== '') {
      const key = code.toLowerCase()
      if (seen.has(key)) {
        errors.code = 'duplicate_code'
        const first = seen.get(key) as number
        result.rows[first].code ??= 'duplicate_code'
      } else seen.set(key, index)
    }

    if (row.contract_category_id === '') errors.contract_category_id = 'required'

    const currency = row.currency.trim()
    if (currency !== '' && !/^[A-Za-z]{3}$/.test(currency)) errors.currency = 'currency_invalid'

    const start = row.start_date.trim()
    const end = row.end_date.trim()
    if (start === '') errors.start_date = 'required'
    else if (!isValidIsoDate(start)) errors.start_date = 'invalid_date'
    if (end !== '') {
      if (!isValidIsoDate(end)) errors.end_date = 'invalid_date'
      else if (!errors.start_date && end < start) errors.end_date = 'end_before_start'
    }

    if (row.notes.trim().length > 5000) errors.notes = 'too_long'
  })

  result.hasErrors =
    result.anchor !== undefined || result.list !== undefined || result.rows.some((r) => Object.keys(r).length > 0)
  return result
}

/** Builds allowlisted snapshot rows; blank optional fields are omitted. Call only after validation passed. */
export function buildContractSnapshotRows(
  rows: readonly ContractDefinitionRowForm[],
): ContractDefinitionSnapshotRow[] {
  return rows.map((row) => {
    const out: ContractDefinitionSnapshotRow = {
      title: row.title.trim(),
      contract_category_id: Number(row.contract_category_id),
      counterparty_kind: row.counterparty_kind,
      start_date: row.start_date.trim(),
    }
    if (row.code.trim() !== '') out.code = row.code.trim()
    if (row.currency.trim() !== '') out.currency = row.currency.trim().toUpperCase()
    if (row.end_date.trim() !== '') out.end_date = row.end_date.trim()
    if (row.notes.trim() !== '') out.notes = row.notes.trim()
    return out
  })
}
