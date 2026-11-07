import { describe, expect, it } from 'vitest'

import type { CopyDecision, SeasonCopyPreview, SeasonCopyPreviewLine } from '../types/seasonCopy'
import {
  allowedDecisions,
  buildPreviewPayload,
  buildResolutions,
  canExecuteCopy,
  canPreviewCopy,
  canShowExecuteButton,
  canViewCopyHistory,
  conflictKey,
  emptyWizardForm,
  isPreviewExpired,
  unresolvedConflicts,
  validateWizardForm,
} from './seasonCopyValidation'

const granted = (...permissions: string[]) => (permission: string) => permissions.includes(permission)

function line(partial: Partial<SeasonCopyPreviewLine> & Pick<SeasonCopyPreviewLine, 'action'>): SeasonCopyPreviewLine {
  return {
    entity_kind: 'season_programs',
    natural_key: 'P1',
    conflict_code: null,
    proposed_resolution: null,
    summary: {},
    ...partial,
  }
}

const NOW = new Date('2026-11-07T12:00:00Z')
const FUTURE = '2026-11-07T12:30:00Z'
const PAST = '2026-11-07T11:00:00Z'

function preview(
  lines: SeasonCopyPreviewLine[],
  overrides: Partial<Pick<SeasonCopyPreview, 'status' | 'expires_at' | 'counts'>> = {},
): Pick<SeasonCopyPreview, 'status' | 'expires_at' | 'lines' | 'counts'> {
  return {
    status: 'valid',
    expires_at: FUTURE,
    lines,
    counts: {
      add: lines.filter((l) => l.action === 'add').length,
      change: lines.filter((l) => l.action === 'change').length,
      exclude: lines.filter((l) => l.action === 'exclude').length,
      conflict: lines.filter((l) => l.action === 'conflict').length,
    },
    ...overrides,
  }
}

describe('validateWizardForm', () => {
  it('requires seasons and kinds', () => {
    expect(validateWizardForm(emptyWizardForm())).toEqual({
      source_season_id: 'required',
      target_season_id: 'required',
      entity_kinds: 'required',
    })
  })

  it('requires a template in from_template mode', () => {
    const errors = validateWizardForm({
      ...emptyWizardForm(),
      mode: 'from_template',
      source_season_id: 1,
      target_season_id: 2,
      entity_kinds: ['season_programs'],
    })
    expect(errors).toEqual({ source_template_id: 'required' })
  })

  it('rejects the same source and target season', () => {
    const errors = validateWizardForm({
      ...emptyWizardForm(),
      source_season_id: 4,
      target_season_id: 4,
      entity_kinds: ['season_programs'],
    })
    expect(errors).toEqual({ target_season_id: 'same_season' })
  })

  it('denies unapproved entity kinds', () => {
    const errors = validateWizardForm({
      ...emptyWizardForm(),
      source_season_id: 1,
      target_season_id: 2,
      entity_kinds: ['season_programs', 'season_pilgrims'],
    })
    expect(errors).toEqual({ entity_kinds: 'denied' })
  })

  it('accepts a complete from_season form', () => {
    expect(
      validateWizardForm({
        ...emptyWizardForm(),
        source_season_id: 1,
        target_season_id: 2,
        entity_kinds: ['season_programs', 'transport_entitlements'],
      }),
    ).toEqual({})
  })
})

describe('buildPreviewPayload (backend contract)', () => {
  it('nests kinds under selection and keeps source_season_id for from_season', () => {
    const payload = buildPreviewPayload({
      mode: 'from_season',
      source_season_id: 1,
      source_template_id: '',
      target_season_id: 2,
      entity_kinds: ['transport_entitlements', 'season_programs'],
    })
    expect(payload).toEqual({
      mode: 'from_season',
      source_season_id: 1,
      target_season_id: 2,
      selection: { entity_kinds: ['season_programs', 'transport_entitlements'] },
      date_mapping: { strategy: 'season_start_delta' },
    })
    expect(payload).not.toHaveProperty('entity_kinds')
    expect(payload).not.toHaveProperty('resolutions')
    expect(payload).not.toHaveProperty('source_template_id')
  })

  it('sends source_template_id and still sends source_season_id for from_template', () => {
    const payload = buildPreviewPayload({
      mode: 'from_template',
      source_season_id: 1,
      source_template_id: 9,
      target_season_id: 2,
      entity_kinds: ['season_programs'],
    })
    expect(payload.source_template_id).toBe(9)
    expect(payload.source_season_id).toBe(1)
  })

  it('drops unapproved kinds from the payload', () => {
    const payload = buildPreviewPayload({
      ...emptyWizardForm(),
      source_season_id: 1,
      target_season_id: 2,
      entity_kinds: ['season_programs', 'finance_ledger'],
    })
    expect(payload.selection.entity_kinds).toEqual(['season_programs'])
  })
})

describe('conflict resolution', () => {
  const dup = line({ action: 'conflict', natural_key: 'A', conflict_code: 'DUPLICATE_KEY' })
  const manual = line({ action: 'conflict', natural_key: 'B', conflict_code: 'MANUAL_MODIFIED' })
  const missing = line({ action: 'conflict', natural_key: 'C', conflict_code: 'MISSING_DEPENDENCY' })

  it('allows overwrite only for overwritable conflicts', () => {
    expect(allowedDecisions(dup)).toEqual(['skip', 'overwrite'])
    expect(allowedDecisions(manual)).toEqual(['skip', 'overwrite'])
    expect(allowedDecisions(missing)).toEqual(['skip'])
  })

  it('lists conflicts that still need a decision', () => {
    const p = preview([dup, manual, missing, line({ action: 'add', natural_key: 'D' })])
    expect(unresolvedConflicts(p, {})).toHaveLength(3)
    expect(unresolvedConflicts(p, { [conflictKey(dup)]: 'skip' })).toHaveLength(2)
  })

  it('treats a disallowed decision as unresolved', () => {
    const p = preview([missing])
    expect(unresolvedConflicts(p, { [conflictKey(missing)]: 'overwrite' })).toHaveLength(1)
    expect(unresolvedConflicts(p, { [conflictKey(missing)]: 'skip' })).toHaveLength(0)
  })

  it('builds resolutions only for conflicts with valid decisions', () => {
    const p = preview([dup, manual, missing])
    const decisions: Record<string, CopyDecision | ''> = {
      [conflictKey(dup)]: 'overwrite',
      [conflictKey(manual)]: '',
      [conflictKey(missing)]: 'overwrite',
    }
    expect(buildResolutions(p, decisions)).toEqual([
      { entity_kind: 'season_programs', natural_key: 'A', decision: 'overwrite' },
    ])
  })
})

describe('canExecuteCopy', () => {
  const add = line({ action: 'add' })
  const conflict = line({ action: 'conflict', natural_key: 'X', conflict_code: 'DUPLICATE_KEY' })
  const execute = granted('season_copy.execute')

  it('requires season_copy.execute', () => {
    expect(canExecuteCopy({ can: granted('season_copy.preview'), preview: preview([add]), decisions: {}, now: NOW })).toBe(false)
    expect(canExecuteCopy({ can: execute, preview: preview([add]), decisions: {}, now: NOW })).toBe(true)
  })

  it('requires a preview', () => {
    expect(canExecuteCopy({ can: execute, preview: null, decisions: {}, now: NOW })).toBe(false)
  })

  it('blocks consumed, invalidated and expired previews', () => {
    expect(canExecuteCopy({ can: execute, preview: preview([add], { status: 'consumed' }), decisions: {}, now: NOW })).toBe(false)
    expect(canExecuteCopy({ can: execute, preview: preview([add], { status: 'invalidated' }), decisions: {}, now: NOW })).toBe(false)
    expect(canExecuteCopy({ can: execute, preview: preview([add], { expires_at: PAST }), decisions: {}, now: NOW })).toBe(false)
  })

  it('blocks while conflicts are unresolved, allows once resolved', () => {
    const p = preview([add, conflict])
    expect(canExecuteCopy({ can: execute, preview: p, decisions: {}, now: NOW })).toBe(false)
    expect(
      canExecuteCopy({ can: execute, preview: p, decisions: { [conflictKey(conflict)]: 'skip' }, now: NOW }),
    ).toBe(true)
  })

  it('blocks when there is nothing to apply', () => {
    expect(
      canExecuteCopy({
        can: execute,
        preview: preview([line({ action: 'exclude', natural_key: 'Z' })]),
        decisions: {},
        now: NOW,
      }),
    ).toBe(false)
  })
})

describe('other permission helpers and expiry', () => {
  it('gates preview, execute button and history individually', () => {
    expect(canPreviewCopy(granted('season_copy.preview'))).toBe(true)
    expect(canPreviewCopy(granted('season_copy.execute'))).toBe(false)
    expect(canShowExecuteButton(granted('season_copy.execute'))).toBe(true)
    expect(canShowExecuteButton(granted('season_copy.preview'))).toBe(false)
    expect(canViewCopyHistory(granted('season_copy.history'))).toBe(true)
    expect(canViewCopyHistory(granted('season_copy.preview'))).toBe(false)
  })

  it('detects expired previews', () => {
    expect(isPreviewExpired({ expires_at: PAST }, NOW)).toBe(true)
    expect(isPreviewExpired({ expires_at: FUTURE }, NOW)).toBe(false)
  })
})
