import type {
  BankAccountFieldErrors,
  BankAccountFormState,
  BankAccountRevealPayload,
  SupplierBankAccount,
  SupplierBankAccountStatus,
} from '../types/banking'

export const BANKING_ERROR_CODES = [
  'SUPPLIER_BANK_IBAN_DUPLICATE',
  'SUPPLIER_BANK_NOT_FOUND',
  'SUPPLIER_BANK_INVALID_STATUS',
  'SUPPLIER_BANK_SOD_VIOLATION',
  'SUPPLIER_BANK_IDENTIFIER_REQUIRED',
  'SUPPLIER_BANK_CURRENCY_UNSUPPORTED',
  'SUPPLIER_BANK_PRIMARY_NOT_VERIFIED',
  'SUPPLIER_BANK_IMMUTABLE',
  'SUPPLIER_BANK_EVIDENCE_INVALID',
  'SUPPLIER_BANK_HOLDER_NAME_REQUIRED',
  'SUPPLIER_BANK_NAME_REQUIRED',
  'SUPPLIER_BANK_REVEAL_REASON_REQUIRED',
] as const

export function mapBankingErrorCode(code: string | undefined): string {
  if (code && (BANKING_ERROR_CODES as readonly string[]).includes(code)) {
    return code
  }
  return 'generic'
}

export function emptyBankAccountForm(): BankAccountFormState {
  return {
    account_holder_name: '',
    bank_name: '',
    label: '',
    iban: '',
    account_number: '',
    currency: 'SAR',
    evidence_document_id: '',
  }
}

/** Prefill metadata for edit — never copies full historical identifiers (masked only in UI). */
export function formFromMaskedAccount(account: SupplierBankAccount): BankAccountFormState {
  return {
    account_holder_name: account.account_holder_name,
    bank_name: account.bank_name,
    label: account.label ?? '',
    iban: '',
    account_number: '',
    currency: account.currency || 'SAR',
    evidence_document_id: account.evidence_document_id ?? '',
  }
}

/** Supersede starts with holder/bank metadata only — identifiers must be re-entered. */
export function formForSupersede(account: SupplierBankAccount): BankAccountFormState {
  return {
    account_holder_name: account.account_holder_name,
    bank_name: account.bank_name,
    label: account.label ?? '',
    iban: '',
    account_number: '',
    currency: 'SAR',
    evidence_document_id: '',
  }
}

export function validateBankAccountForm(form: BankAccountFormState): BankAccountFieldErrors {
  const errors: BankAccountFieldErrors = {}
  if (!form.account_holder_name.trim()) {
    errors.account_holder_name = 'holderRequired'
  }
  if (!form.bank_name.trim()) {
    errors.bank_name = 'bankRequired'
  }
  const iban = form.iban.trim()
  const local = form.account_number.trim()
  if (!iban && !local) {
    errors.identifier = 'identifierRequired'
  }
  const currency = form.currency.trim().toUpperCase()
  if (currency && currency !== 'SAR') {
    errors.currency = 'currencySarOnly'
  }
  if (
    form.evidence_document_id !== '' &&
    (!Number.isFinite(Number(form.evidence_document_id)) || Number(form.evidence_document_id) < 1)
  ) {
    errors.evidence_document_id = 'evidenceInvalid'
  }
  return errors
}

export function validateReasonMin10(reason: string): BankAccountFieldErrors {
  const trimmed = reason.trim()
  if (trimmed.length < 10) {
    return { reason: 'reasonMin10' }
  }
  return {}
}

export function bankingStatusBadgeClass(status: SupplierBankAccountStatus | string): string {
  switch (status) {
    case 'pending':
      return 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
    case 'verified':
      return 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    case 'rejected':
      return 'bg-red-100 text-red-950 ring-1 ring-inset ring-red-300/80'
    case 'revoked':
      return 'bg-slate-200/80 text-slate-800 ring-1 ring-inset ring-slate-400/50'
    default:
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-300/80'
  }
}

export function canEditPendingAccount(account: SupplierBankAccount): boolean {
  return account.status === 'pending' && account.is_current
}

export function canVerifyOrRejectAccount(account: SupplierBankAccount): boolean {
  return account.status === 'pending' && account.is_current
}

export function canSupersedeAccount(account: SupplierBankAccount): boolean {
  return account.status === 'verified' && account.is_current
}

export function canRevokeAccount(account: SupplierBankAccount): boolean {
  return account.status === 'verified'
}

export function canSetPrimaryAccount(account: SupplierBankAccount): boolean {
  return account.status === 'verified' && !account.is_primary
}

export function canClearPrimaryAccount(account: SupplierBankAccount): boolean {
  return account.is_primary === true
}

/** Sensitive keys that must never appear in masked UI / storage / logs. */
export const FORBIDDEN_BANK_SENSITIVE_KEYS = [
  'iban',
  'account_number',
  'iban_ciphertext',
  'account_ciphertext',
  'iban_fingerprint',
  'fingerprint',
  'pepper',
  'BANKING_IBAN_PEPPER',
] as const

export function containsForbiddenBankSensitiveKeys(value: unknown, depth = 0): boolean {
  if (value == null || depth > 6) return false
  if (Array.isArray(value)) {
    return value.some((item) => containsForbiddenBankSensitiveKeys(item, depth + 1))
  }
  if (typeof value === 'object') {
    for (const [key, nested] of Object.entries(value as Record<string, unknown>)) {
      if ((FORBIDDEN_BANK_SENSITIVE_KEYS as readonly string[]).includes(key)) {
        return true
      }
      if (containsForbiddenBankSensitiveKeys(nested, depth + 1)) {
        return true
      }
    }
  }
  return false
}

export function assertMaskedBankAccount(account: SupplierBankAccount): boolean {
  return (
    !('iban' in account) &&
    !('account_number' in account) &&
    !('iban_ciphertext' in account) &&
    !('iban_fingerprint' in account) &&
    !('account_ciphertext' in account)
  )
}

export function isRevealPayloadEmpty(payload: BankAccountRevealPayload | null): boolean {
  return payload == null
}

export function scrubRevealPayload(payload: BankAccountRevealPayload | null): BankAccountRevealPayload | null {
  if (!payload) return null
  // Explicit wipe pattern for callers clearing ephemeral state.
  payload.iban = null
  payload.account_number = null
  return null
}

export type BankingListViewState = 'loading' | 'error' | 'empty' | 'ready' | 'forbidden'

export function resolveBankingListState(options: {
  canView: boolean
  isLoading: boolean
  isError: boolean
  count: number
}): BankingListViewState {
  if (!options.canView) return 'forbidden'
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  if (options.count === 0) return 'empty'
  return 'ready'
}

export type BankingUiPermissions = {
  canManage: boolean
  canVerify: boolean
  canReveal: boolean
}

/** Affordance matrix — backend remains authoritative. */
export function bankingActionVisibility(
  account: SupplierBankAccount,
  perms: BankingUiPermissions,
): {
  edit: boolean
  verify: boolean
  reject: boolean
  revoke: boolean
  supersede: boolean
  setPrimary: boolean
  clearPrimary: boolean
  reveal: boolean
} {
  return {
    edit: perms.canManage && canEditPendingAccount(account),
    verify: perms.canVerify && canVerifyOrRejectAccount(account),
    reject: perms.canVerify && canVerifyOrRejectAccount(account),
    revoke: perms.canManage && canRevokeAccount(account),
    supersede: perms.canManage && canSupersedeAccount(account),
    setPrimary: perms.canManage && canSetPrimaryAccount(account),
    clearPrimary: perms.canManage && canClearPrimaryAccount(account),
    reveal: perms.canReveal,
  }
}
