export type SupplierBankAccountStatus = 'pending' | 'verified' | 'rejected' | 'revoked'

/** Masked bank account projection from list/show/mutation responses (never plaintext identifiers). */
export interface SupplierBankAccount {
  id: number
  supplier_id: number
  account_key: string
  version_no: number
  label: string | null
  account_holder_name: string
  bank_name: string
  iban_masked: string | null
  account_masked: string | null
  currency: string
  status: SupplierBankAccountStatus
  is_primary: boolean
  is_current: boolean
  supersedes_id: number | null
  evidence_document_id: number | null
  created_by: number | null
  last_edited_by?: number | null
  verified_by: number | null
  rejected_by?: number | null
  revoked_by?: number | null
  verified_at: string | null
  rejected_at?: string | null
  revoked_at?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface BankAccountFormState {
  account_holder_name: string
  bank_name: string
  label: string
  iban: string
  account_number: string
  currency: string
  evidence_document_id: number | ''
}

export interface CreateBankAccountPayload {
  account_holder_name: string
  bank_name: string
  label?: string | null
  iban?: string | null
  account_number?: string | null
  currency?: string
  evidence_document_id?: number | null
}

export type UpdatePendingBankAccountPayload = CreateBankAccountPayload
export type SupersedeBankAccountPayload = CreateBankAccountPayload

/** Ephemeral reveal response — never cache or persist. */
export interface BankAccountRevealPayload {
  id: number
  supplier_id: number
  account_key: string
  version_no: number
  status: SupplierBankAccountStatus | string | null
  iban: string | null
  account_number: string | null
  iban_last4: string | null
  account_last4: string | null
}

export interface BankAccountFieldErrors {
  account_holder_name?: string
  bank_name?: string
  identifier?: string
  currency?: string
  evidence_document_id?: string
  reason?: string
}
