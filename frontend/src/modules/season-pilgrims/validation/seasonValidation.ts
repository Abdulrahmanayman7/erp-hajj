import type { Season, SeasonFormState, SeasonStatus } from '../types/seasons'

export interface SeasonFieldErrors {
  code?: string
  name?: string
  hajj_year?: string
  operational_starts_on?: string
  operational_ends_on?: string
  notes?: string
  reason?: string
}

const CODE_RE = /^[A-Za-z0-9_\-]+$/

export function validateSeasonForm(form: SeasonFormState): SeasonFieldErrors {
  const errors: SeasonFieldErrors = {}

  const code = form.code.trim()
  if (!code) {
    errors.code = 'required'
  } else if (code.length > 40 || !CODE_RE.test(code)) {
    errors.code = 'format'
  }

  if (!form.name.trim()) {
    errors.name = 'required'
  } else if (form.name.trim().length > 255) {
    errors.name = 'max'
  }

  const year = Number(form.hajj_year)
  if (!form.hajj_year.trim() || !Number.isInteger(year) || year < 1400 || year > 1600) {
    errors.hajj_year = 'range'
  }

  if (!form.operational_starts_on) {
    errors.operational_starts_on = 'required'
  }
  if (!form.operational_ends_on) {
    errors.operational_ends_on = 'required'
  }
  if (
    form.operational_starts_on &&
    form.operational_ends_on &&
    form.operational_ends_on < form.operational_starts_on
  ) {
    errors.operational_ends_on = 'after'
  }

  if (form.notes.trim().length > 5000) {
    errors.notes = 'max'
  }

  return errors
}

export function canEditSeason(season: Season | null | undefined): boolean {
  return season != null && season.status !== 'closed'
}

export function canActivateSeason(season: Season): boolean {
  return season.status === 'draft'
}

export function canCloseSeason(season: Season): boolean {
  return season.status === 'active'
}

export function canReopenSeason(season: Season): boolean {
  return season.status === 'closed'
}

export type SeasonsListViewState = 'loading' | 'error' | 'empty' | 'ready'

export function resolveSeasonsListState(options: {
  isLoading: boolean
  isError: boolean
  count: number
}): SeasonsListViewState {
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  if (options.count === 0) return 'empty'
  return 'ready'
}

export function canShowCreateSeasonCta(permissions: string[]): boolean {
  return permissions.includes('seasons.create')
}

export function emptySeasonForm(): SeasonFormState {
  return {
    code: '',
    name: '',
    hajj_year: '',
    operational_starts_on: '',
    operational_ends_on: '',
    notes: '',
  }
}

export function statusBadgeClass(status: SeasonStatus): string {
  switch (status) {
    case 'active':
      return 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/70'
    case 'draft':
      return 'bg-amber-50 text-amber-800 ring-1 ring-amber-200/70'
    case 'closed':
      return 'bg-neutral-100 text-neutral-600 ring-1 ring-neutral-200/80'
    default:
      return 'bg-neutral-100 text-neutral-600 ring-1 ring-neutral-200/80'
  }
}
