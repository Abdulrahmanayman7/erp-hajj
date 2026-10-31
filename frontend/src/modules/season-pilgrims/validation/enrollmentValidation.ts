import type { EnrollFormState, ParticipationEditState } from '../types/enrollment'
import type { PilgrimGroupFormState } from '../types/pilgrimGroups'
import type { ProgramFormState } from '../types/programs'
import type { FamilyFormState } from '../types/families'

export function validateEnrollForm(form: EnrollFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (form.pilgrim_id === '' || form.pilgrim_id == null) {
    errors.pilgrim_id = 'required'
  }
  return errors
}

export function mapEnrollmentErrorCode(code: string | undefined): string {
  switch (code) {
    case 'SEASON_PILGRIM_ALREADY_REGISTERED':
      return 'alreadyRegistered'
    case 'SEASON_CLOSED':
      return 'seasonClosed'
    case 'PROGRAM_INVALID':
    case 'PROGRAM_INACTIVE':
      return 'programInvalid'
    case 'PILGRIM_GROUP_INVALID':
      return 'groupInvalid'
    case 'FAMILY_GROUP_INVALID':
      return 'familyInvalid'
    default:
      return 'generic'
  }
}

export function validateCodeNameForm(
  form: { code: string; name: string },
): Record<string, string> {
  const errors: Record<string, string> = {}
  const code = form.code.trim()
  if (!code) errors.code = 'required'
  else if (code.length > 40 || !/^[A-Za-z0-9_\-]+$/.test(code)) errors.code = 'format'
  if (!form.name.trim()) errors.name = 'required'
  else if (form.name.trim().length > 255) errors.name = 'max'
  return errors
}

export function validatePilgrimGroupForm(form: PilgrimGroupFormState): Record<string, string> {
  return validateCodeNameForm(form)
}

export function validateProgramForm(form: ProgramFormState): Record<string, string> {
  return validateCodeNameForm(form)
}

export function validateFamilyForm(form: FamilyFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!form.name.trim()) errors.name = 'required'
  else if (form.name.trim().length > 255) errors.name = 'max'
  return errors
}

export function emptyEnrollForm(pilgrimId: number | '' = ''): EnrollFormState {
  return {
    pilgrim_id: pilgrimId,
    season_program_id: '',
    season_pilgrim_group_id: '',
    season_family_group_id: '',
  }
}

export function emptyParticipationEdit(
  partial: Partial<ParticipationEditState> = {},
): ParticipationEditState {
  return {
    season_program_id: '',
    season_pilgrim_group_id: '',
    season_family_group_id: '',
    program_reason: '',
    ...partial,
  }
}

export type ListViewState = 'loading' | 'error' | 'empty' | 'ready' | 'need_season'

export function resolveSeasonScopedListState(options: {
  hasSeason: boolean
  isLoading: boolean
  isError: boolean
  count: number
}): ListViewState {
  if (!options.hasSeason) return 'need_season'
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  if (options.count === 0) return 'empty'
  return 'ready'
}
