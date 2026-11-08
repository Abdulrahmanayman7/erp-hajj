/**
 * Safe-copy matrix (S08). Approved entity kinds only — the backend allowlist
 * (`SafeCopyAllowlist`, MATRIX_VERSION 2026-11-08.2, 10 kinds) is authoritative; this list is a UX mirror.
 * Order is dependency order (projects before phases before tasks). `season_reminder_rules` (S08-D11..D16)
 * copies as draft rules only; the legacy `reminder_rules` alias and notifications stay denied.
 */
export const APPROVED_ENTITY_KINDS = [
  'season_programs',
  'season_pilgrim_groups',
  'transport_entitlements',
  'transport_season_vehicles',
  'accommodation_season_facilities',
  'contract_definitions',
  'season_projects',
  'season_project_phases',
  'task_definitions',
  'season_reminder_rules',
] as const

export type ApprovedEntityKind = (typeof APPROVED_ENTITY_KINDS)[number]

/** Kinds that exist only as template snapshots (no live source rows to copy from). UX mirror of the backend rule. */
export const SNAPSHOT_ONLY_KINDS: readonly string[] = ['contract_definitions']

export type CopyMode = 'from_season' | 'from_template'
export const COPY_MODES: readonly CopyMode[] = ['from_season', 'from_template']

export type PreviewStatus = 'valid' | 'invalidated' | 'consumed' | 'expired'
export type CopyRunStatus = 'succeeded' | 'failed'

export type PreviewLineAction = 'add' | 'change' | 'exclude' | 'conflict'
export type ConflictCode = 'DUPLICATE_KEY' | 'MANUAL_MODIFIED' | 'MISSING_DEPENDENCY' | 'TARGET_LOCKED' | string

export type CopyDecision = 'skip' | 'overwrite'

export type CopyRunLineResult = 'added' | 'changed' | 'overwritten' | 'skipped' | 'excluded'

export type ListViewState = 'loading' | 'error' | 'empty' | 'ready'

/** Allowlisted, non-PII summary. `fields` for adds, `diff` for change/conflict, `detail` for dependency conflicts. */
export interface PreviewLineSummary {
  fields?: Record<string, unknown>
  diff?: Record<string, { before: unknown; after: unknown }>
  detail?: Record<string, unknown>
  reason_code?: string
  /** Date remap result per field (season_start_delta), present when a line carries dates. */
  date_transforms?: Record<string, { before: unknown; after: unknown }>
  [key: string]: unknown
}

export interface SeasonCopyPreviewLine {
  entity_kind: string
  action: PreviewLineAction
  natural_key: string
  conflict_code: ConflictCode | null
  proposed_resolution: string | null
  summary: PreviewLineSummary
}

export interface SeasonCopyPreviewCounts {
  add: number
  change: number
  exclude: number
  conflict: number
}

export interface SeasonCopyPreview {
  id: number
  preview_token: string
  mode: CopyMode
  /** Null when copying from a snapshot-complete template without a live source season. */
  source_season_id: number | null
  source_template_id: number | null
  target_season_id: number
  matrix_version: string
  content_hash: string
  status: PreviewStatus
  expires_at: string
  selection: { entity_kinds: string[]; codes: Record<string, string[]> } | null
  date_mapping: Record<string, unknown> | null
  counts: SeasonCopyPreviewCounts | null
  lines: SeasonCopyPreviewLine[]
  exclusions: Array<{ entity_kind: string; natural_key: string; reason_code: string | null }>
  created_at: string | null
}

/**
 * Body of POST /season-copy/previews.
 * NOTE: the backend takes `selection.entity_kinds` (not a top-level `entity_kinds`),
 * requires `source_season_id` in `from_season` mode (optional in `from_template` when the template
 * snapshots cover every selected kind), and does not accept `resolutions` here
 * (resolutions belong to the execute call).
 */
export interface CreateSeasonCopyPreviewPayload {
  mode: CopyMode
  source_season_id?: number
  target_season_id: number
  source_template_id?: number
  selection: { entity_kinds: string[] }
  date_mapping: { strategy: 'season_start_delta' }
}

export interface CopyResolution {
  entity_kind: string
  natural_key: string
  decision: CopyDecision
}

export interface ExecuteSeasonCopyPayload {
  preview_token: string
  content_hash: string
  resolutions?: CopyResolution[]
}

export interface SeasonCopyRunCounts {
  added: number
  changed: number
  skipped: number
  excluded: number
}

export interface SeasonCopyRunLine {
  entity_kind: string
  natural_key: string
  result: CopyRunLineResult | string
  reason_code: string | null
  source_row_id: number | null
  target_row_id: number | null
}

export interface SeasonCopyRun {
  id: number
  run_number: string
  preview_id: number
  mode: CopyMode
  source_season_id: number | null
  source_template_id: number | null
  target_season_id: number
  status: CopyRunStatus
  error_code: string | null
  error_message: string | null
  counts: SeasonCopyRunCounts | null
  correlation_id: string
  started_at: string
  finished_at: string | null
  created_by: number | null
  /** Present on the detail / execute response only. */
  lines?: SeasonCopyRunLine[]
}

export interface ListSeasonCopyRunsParams {
  target_season_id?: number | ''
  status?: CopyRunStatus | ''
  from?: string
  to?: string
  page?: number
  per_page?: number
}

export interface SeasonCopyListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface SeasonCopyWizardForm {
  mode: CopyMode
  source_season_id: number | ''
  source_template_id: number | ''
  target_season_id: number | ''
  entity_kinds: string[]
}
