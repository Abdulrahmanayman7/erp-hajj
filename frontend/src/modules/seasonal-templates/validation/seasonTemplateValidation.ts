import {
  APPROVED_ENTITY_KINDS,
  SNAPSHOT_ONLY_KINDS,
  type ApprovedEntityKind,
  type ListViewState,
} from '../types/seasonCopy'
import type {
  SeasonTemplate,
  TemplateFormState,
  TemplateItemPayload,
  TemplateStatus,
} from '../types/seasonTemplates'
import { buildContractSnapshotRows, hasContractRows, validateContractRows } from './contractDefinitionRows'

type Can = (permission: string) => boolean

export function isApprovedEntityKind(kind: string): kind is ApprovedEntityKind {
  return (APPROVED_ENTITY_KINDS as readonly string[]).includes(kind)
}

/** Keeps approved kinds only, in canonical (dependency) order, without duplicates. */
export function filterApprovedEntityKinds(kinds: readonly string[]): ApprovedEntityKind[] {
  return APPROVED_ENTITY_KINDS.filter((kind) => kinds.includes(kind))
}

/** Kinds that are not on the approved list (must never be sent). */
export function deniedEntityKinds(kinds: readonly string[]): string[] {
  return kinds.filter((kind) => !isApprovedEntityKind(kind))
}

export function emptyTemplateForm(): TemplateFormState {
  return {
    name: '',
    description: '',
    entity_kinds: [],
    codes: {},
    capture_season_id: '',
    contract_anchor: '',
    contract_rows: [],
  }
}

export function templateToForm(template: SeasonTemplate): TemplateFormState {
  const codes: Record<string, string> = {}
  template.items.forEach((item) => {
    if (item.selection?.codes?.length) codes[item.entity_kind] = item.selection.codes.join('\n')
  })
  return {
    name: template.name,
    description: template.description ?? '',
    entity_kinds: filterApprovedEntityKinds(template.items.map((item) => item.entity_kind)),
    codes,
    capture_season_id: '',
    // Stored snapshot rows are never returned to the browser; leaving the editor empty keeps them (server-side).
    contract_anchor: '',
    contract_rows: [],
  }
}

/**
 * Optional capture of independent snapshots from a season while saving. Only the season id is
 * sent — snapshot rows are produced and validated by the server, never assembled in the browser.
 */
export function buildCaptureSeason(form: Pick<TemplateFormState, 'capture_season_id'>): { capture_season_id?: number } {
  return form.capture_season_id === '' ? {} : { capture_season_id: Number(form.capture_season_id) }
}

/** Selected kinds that exist only as snapshots and therefore need a captured/stored snapshot to be usable. */
export function snapshotOnlySelected(kinds: readonly string[]): string[] {
  return kinds.filter((kind) => SNAPSHOT_ONLY_KINDS.includes(kind))
}

export function parseCodes(raw: string | undefined): string[] {
  if (!raw) return []
  return [
    ...new Set(
      raw
        .split(/[\n,،]/)
        .map((code) => code.trim())
        .filter((code) => code !== ''),
    ),
  ]
}

export function validateTemplateForm(form: TemplateFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  const name = form.name.trim()
  if (!name) errors.name = 'required'
  else if (name.length > 200) errors.name = 'too_long'
  if (form.description.trim().length > 5000) errors.description = 'too_long'
  if (form.entity_kinds.length === 0) errors.entity_kinds = 'required'
  else if (deniedEntityKinds(form.entity_kinds).length > 0) errors.entity_kinds = 'denied'
  else if (form.entity_kinds.length > 20) errors.entity_kinds = 'too_many'
  if (form.entity_kinds.includes('contract_definitions') && validateContractRows(form.contract_rows, form.contract_anchor).hasErrors) {
    errors.contract_rows = 'contract_rows_invalid'
  }
  return errors
}

/** Builds API items from approved kinds only; unapproved kinds are dropped, never sent. Snapshot rows are attached for contract_definitions only. */
export function buildTemplateItems(form: TemplateFormState): TemplateItemPayload[] {
  return filterApprovedEntityKinds(form.entity_kinds).map((kind) => {
    const codes = parseCodes(form.codes[kind])
    const item: TemplateItemPayload = codes.length > 0 ? { entity_kind: kind, selection: { codes } } : { entity_kind: kind }
    // Only contract definitions are typed in the browser (snapshot-only kind); every other snapshot is server-captured.
    if (kind === 'contract_definitions' && hasContractRows(form.contract_rows)) {
      item.snapshot = { anchor: form.contract_anchor.trim(), rows: buildContractSnapshotRows(form.contract_rows) }
    }
    return item
  })
}

/* ---------------------------------------------------------------- permissions (UX only) */

export function canViewTemplates(can: Can): boolean {
  return can('season_templates.view')
}

export function canShowCreateTemplate(can: Can): boolean {
  return can('season_templates.manage')
}

export function canEditTemplate(status: TemplateStatus, can: Can): boolean {
  return can('season_templates.manage') && status === 'draft'
}

export function canApproveTemplate(template: Pick<SeasonTemplate, 'status' | 'items'>, can: Can): boolean {
  return can('season_templates.manage') && template.status === 'draft' && template.items.length > 0
}

export function canArchiveTemplate(status: TemplateStatus, can: Can): boolean {
  return can('season_templates.manage') && (status === 'draft' || status === 'approved')
}

export function canShowTemplateSource(can: Can): boolean {
  return can('season_templates.view')
}

/* ---------------------------------------------------------------- display helpers */

export function templateStatusBadgeClass(status: TemplateStatus): string {
  switch (status) {
    case 'draft':
      return 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
    case 'approved':
      return 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    case 'archived':
    default:
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-300/80'
  }
}

export function resolveListState(options: {
  isLoading: boolean
  isError: boolean
  count: number
}): ListViewState {
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  return options.count === 0 ? 'empty' : 'ready'
}

const KNOWN_ERROR_CODES = [
  'SEASON_COPY_TARGET_NOT_DRAFT',
  'SEASON_COPY_SOURCE_INVALID',
  'SEASON_COPY_PREVIEW_STALE',
  'SEASON_COPY_KIND_DENIED',
  'SEASON_COPY_CONFLICT_UNRESOLVED',
  'SEASON_COPY_PREVIEW_REQUIRED',
  'SEASON_COPY_SOURCE_REQUIRED',
  'SEASON_COPY_SNAPSHOT_REQUIRED',
  'SEASON_TEMPLATE_SNAPSHOT_INVALID',
  'SEASON_TEMPLATE_INVALID_TRANSITION',
  'SEASON_NUMBER_UNAVAILABLE',
  'VALIDATION_ERROR',
] as const

/** Maps a backend `code` to a locale key suffix; unknown codes fall back to `generic`. */
export function mapSeasonalTemplatesErrorCode(code: string | undefined): string {
  return (KNOWN_ERROR_CODES as readonly string[]).includes(code ?? '') ? code! : 'generic'
}
