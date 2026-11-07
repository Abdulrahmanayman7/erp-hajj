import type {
  CopyDecision,
  CopyResolution,
  CreateSeasonCopyPreviewPayload,
  SeasonCopyPreview,
  SeasonCopyPreviewLine,
  SeasonCopyRun,
  SeasonCopyWizardForm,
} from '../types/seasonCopy'
import { deniedEntityKinds, filterApprovedEntityKinds } from './seasonTemplateValidation'

type Can = (permission: string) => boolean

/** Mirror of the backend rule: only these conflicts allow an explicit `overwrite`. */
const OVERWRITABLE_CONFLICTS = ['DUPLICATE_KEY', 'MANUAL_MODIFIED']

export function emptyWizardForm(): SeasonCopyWizardForm {
  return {
    mode: 'from_season',
    source_season_id: '',
    source_template_id: '',
    target_season_id: '',
    entity_kinds: [],
  }
}

/** Minimal template shape needed to decide whether a live source season is still required. */
export interface SnapshotCoverageTemplate {
  items: ReadonlyArray<{ entity_kind: string; has_snapshot?: boolean }>
}

/**
 * UX hint mirroring the backend rule: a live source season is optional in `from_template` mode
 * only when the template carries a snapshot for every selected kind. The server stays authoritative.
 */
export function sourceSeasonRequired(
  form: Pick<SeasonCopyWizardForm, 'mode' | 'entity_kinds'>,
  template?: SnapshotCoverageTemplate | null,
): boolean {
  if (form.mode !== 'from_template' || !template) return true
  const snapshotKinds = new Set(template.items.filter((item) => item.has_snapshot).map((item) => item.entity_kind))
  return form.entity_kinds.length === 0 || form.entity_kinds.some((kind) => !snapshotKinds.has(kind))
}

export function validateWizardForm(
  form: SeasonCopyWizardForm,
  template?: SnapshotCoverageTemplate | null,
): Record<string, string> {
  const errors: Record<string, string> = {}
  if (form.source_season_id === '' && sourceSeasonRequired(form, template)) errors.source_season_id = 'required'
  if (form.target_season_id === '') errors.target_season_id = 'required'
  if (
    form.source_season_id !== '' &&
    form.target_season_id !== '' &&
    form.source_season_id === form.target_season_id
  ) {
    errors.target_season_id = 'same_season'
  }
  if (form.mode === 'from_template' && form.source_template_id === '') {
    errors.source_template_id = 'required'
  }
  if (form.entity_kinds.length === 0) errors.entity_kinds = 'required'
  else if (deniedEntityKinds(form.entity_kinds).length > 0) errors.entity_kinds = 'denied'
  return errors
}

/**
 * Backend contract: `selection.entity_kinds`, `source_season_id` when chosen (required in
 * from_season), `source_template_id` only for from_template. Unapproved kinds are never sent.
 */
export function buildPreviewPayload(form: SeasonCopyWizardForm): CreateSeasonCopyPreviewPayload {
  const payload: CreateSeasonCopyPreviewPayload = {
    mode: form.mode,
    target_season_id: Number(form.target_season_id),
    selection: { entity_kinds: filterApprovedEntityKinds(form.entity_kinds) },
    date_mapping: { strategy: 'season_start_delta' },
  }
  if (form.source_season_id !== '') payload.source_season_id = Number(form.source_season_id)
  if (form.mode === 'from_template') payload.source_template_id = Number(form.source_template_id)
  return payload
}

/* ---------------------------------------------------------------- conflicts */

export function conflictKey(line: Pick<SeasonCopyPreviewLine, 'entity_kind' | 'natural_key'>): string {
  return `${line.entity_kind}\u0000${line.natural_key}`
}

export function conflictLines(preview: Pick<SeasonCopyPreview, 'lines'> | null | undefined): SeasonCopyPreviewLine[] {
  return (preview?.lines ?? []).filter((line) => line.action === 'conflict')
}

export function allowedDecisions(line: Pick<SeasonCopyPreviewLine, 'conflict_code'>): CopyDecision[] {
  return OVERWRITABLE_CONFLICTS.includes(line.conflict_code ?? '') ? ['skip', 'overwrite'] : ['skip']
}

export function unresolvedConflicts(
  preview: Pick<SeasonCopyPreview, 'lines'> | null | undefined,
  decisions: Record<string, CopyDecision | ''>,
): SeasonCopyPreviewLine[] {
  return conflictLines(preview).filter((line) => {
    const decision = decisions[conflictKey(line)]
    return !decision || !allowedDecisions(line).includes(decision)
  })
}

export function buildResolutions(
  preview: Pick<SeasonCopyPreview, 'lines'>,
  decisions: Record<string, CopyDecision | ''>,
): CopyResolution[] {
  return conflictLines(preview).flatMap((line) => {
    const decision = decisions[conflictKey(line)]
    return decision && allowedDecisions(line).includes(decision)
      ? [{ entity_kind: line.entity_kind, natural_key: line.natural_key, decision }]
      : []
  })
}

export function isPreviewExpired(preview: Pick<SeasonCopyPreview, 'expires_at'>, now: Date = new Date()): boolean {
  const expires = new Date(preview.expires_at).getTime()
  return Number.isFinite(expires) && expires <= now.getTime()
}

/** Whether there is anything the backend would actually write (adds/changes or resolved overwrites). */
export function hasApplicableChanges(preview: Pick<SeasonCopyPreview, 'counts'>): boolean {
  const counts = preview.counts
  return !!counts && counts.add + counts.change + counts.conflict > 0
}

/* ---------------------------------------------------------------- permissions (UX only) */

export function canPreviewCopy(can: Can): boolean {
  return can('season_copy.preview')
}

export function canViewCopyHistory(can: Can): boolean {
  return can('season_copy.history')
}

/** Client hint only — server enforces permission, staleness and conflict resolution. */
export function canExecuteCopy(options: {
  can: Can
  preview: Pick<SeasonCopyPreview, 'status' | 'expires_at' | 'lines' | 'counts'> | null | undefined
  decisions: Record<string, CopyDecision | ''>
  now?: Date
}): boolean {
  const { can, preview, decisions } = options
  if (!can('season_copy.execute')) return false
  if (!preview || preview.status !== 'valid') return false
  if (isPreviewExpired(preview, options.now)) return false
  if (unresolvedConflicts(preview, decisions).length > 0) return false
  return hasApplicableChanges(preview)
}

export function canShowExecuteButton(can: Can): boolean {
  return can('season_copy.execute')
}

/* ---------------------------------------------------------------- display helpers */

export function runStatusBadgeClass(status: SeasonCopyRun['status']): string {
  return status === 'succeeded'
    ? 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    : 'bg-red-100 text-red-950 ring-1 ring-inset ring-red-300/80'
}

export function lineActionBadgeClass(action: SeasonCopyPreviewLine['action']): string {
  switch (action) {
    case 'add':
      return 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    case 'change':
      return 'bg-sky-100 text-sky-950 ring-1 ring-inset ring-sky-300/80'
    case 'conflict':
      return 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
    case 'exclude':
    default:
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-300/80'
  }
}

/** Date remap rows (field, before, after) the preview shows for `season_start_delta`. */
export function dateTransformRows(
  line: Pick<SeasonCopyPreviewLine, 'summary'>,
): Array<{ field: string; before: string; after: string }> {
  const transforms = line.summary?.date_transforms
  if (!transforms) return []
  return Object.entries(transforms).map(([field, change]) => ({
    field,
    before: formatSummaryValue(change.before),
    after: formatSummaryValue(change.after),
  }))
}

export function formatSummaryValue(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'boolean') return value ? 'نعم' : 'لا'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}
