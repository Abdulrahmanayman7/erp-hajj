import type { PilgrimFormState } from '../types/pilgrims'

export interface PilgrimFieldErrors {
  full_name?: string
  gender?: string
  nationality?: string
  phone?: string
  notes?: string
}

const NATIONALITY_RE = /^[A-Za-z]{2,3}$/

export function validatePilgrimForm(form: PilgrimFormState): PilgrimFieldErrors {
  const errors: PilgrimFieldErrors = {}

  if (!form.full_name.trim()) {
    errors.full_name = 'required'
  } else if (form.full_name.trim().length > 255) {
    errors.full_name = 'max'
  }

  if (form.gender !== '' && form.gender !== 'male' && form.gender !== 'female') {
    errors.gender = 'invalid'
  }

  const nationality = form.nationality.trim()
  if (nationality && !NATIONALITY_RE.test(nationality)) {
    errors.nationality = 'format'
  }

  if (form.phone.trim().length > 32) {
    errors.phone = 'max'
  }

  if (form.notes.trim().length > 5000) {
    errors.notes = 'max'
  }

  return errors
}

export function mapPilgrimErrorCode(code: string | undefined): string {
  switch (code) {
    default:
      return 'generic'
  }
}

export type PilgrimsListViewState = 'loading' | 'error' | 'empty' | 'ready'

export function resolvePilgrimsListState(options: {
  isLoading: boolean
  isError: boolean
  count: number
}): PilgrimsListViewState {
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  if (options.count === 0) return 'empty'
  return 'ready'
}

export function canShowCreatePilgrimCta(permissions: string[]): boolean {
  return permissions.includes('pilgrims.create')
}

export function canShowEditPilgrimCta(permissions: string[]): boolean {
  return permissions.includes('pilgrims.update')
}

export function canViewIdentityDocuments(permissions: string[]): boolean {
  return (
    permissions.includes('pilgrims.manage_identity_documents') ||
    permissions.includes('pilgrims.view')
  )
}

export function canRevealIdentityDocumentNumbers(permissions: string[]): boolean {
  return permissions.includes('pilgrims.manage_identity_documents')
}

export function emptyPilgrimForm(): PilgrimFormState {
  return {
    full_name: '',
    gender: '',
    nationality: '',
    phone: '',
    notes: '',
  }
}
