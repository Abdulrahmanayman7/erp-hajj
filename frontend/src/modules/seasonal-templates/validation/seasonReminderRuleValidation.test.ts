import { describe, expect, it } from 'vitest'

import { seasonalTemplatesAr } from '@/locales/seasonalTemplatesAr'

import {
  APPROVED_RECIPIENT_PERMISSIONS,
  type ReminderRuleFormState,
  type SeasonReminderRule,
} from '../types/seasonReminderRules'
import {
  addDaysToDate,
  buildCreatePayload,
  buildUpdatePayload,
  canActivateReminderRule,
  canArchiveReminderRule,
  canEditReminderRule,
  canManageReminderRules,
  canPauseReminderRule,
  canViewReminderRules,
  computeFireDate,
  computeFormFireDate,
  emptyReminderRuleForm,
  hasUnapprovedRecipientPermission,
  isApprovedRecipientPermission,
  isValidDateOnly,
  mapReminderErrorCode,
  resolveReminderListState,
  ruleToForm,
  validateReminderRuleForm,
} from './seasonReminderRuleValidation'

const granted = (...permissions: string[]) => (permission: string) => permissions.includes(permission)

function validForm(overrides: Partial<ReminderRuleFormState> = {}): ReminderRuleFormState {
  return {
    ...emptyReminderRuleForm(),
    code: 'ARAFAH-1',
    title: 'تذكير عرفة',
    trigger_mode: 'offset_from_season_start',
    offset_days: '-3',
    recipient_permission: 'tasks.view',
    ...overrides,
  }
}

const rule: SeasonReminderRule = {
  id: 7,
  season_id: 1,
  season: null,
  code: 'R-1',
  title: 'عنوان',
  body: null,
  severity: 'warning',
  status: 'draft',
  trigger_mode: 'absolute_date',
  trigger_on: '2027-05-20',
  offset_days: null,
  recipient_mode: 'permission_holders',
  recipient_permission: 'meetings.view',
  created_by: null,
  created_at: null,
  updated_at: null,
}

describe('recipient permission allowlist (UX mirror of the backend)', () => {
  it('mirrors exactly the nine approved view permissions', () => {
    expect([...APPROVED_RECIPIENT_PERMISSIONS]).toEqual([
      'seasons.view',
      'season_templates.view',
      'season_projects.view',
      'tasks.view',
      'contracts.view',
      'meetings.view',
      'decisions.view',
      'season_copy.history',
      'season_reminders.view',
    ])
  })

  it('never contains administrative, manage, approve or delete permissions', () => {
    for (const permission of APPROVED_RECIPIENT_PERMISSIONS) {
      expect(permission).not.toMatch(/^(users|roles|tenant_settings|platform_)/)
      expect(permission).not.toMatch(/\.(manage|approve|delete|execute)$/)
    }
  })

  it('accepts only allowlisted values', () => {
    expect(isApprovedRecipientPermission('tasks.view')).toBe(true)
    expect(isApprovedRecipientPermission('users.view')).toBe(false)
    expect(isApprovedRecipientPermission('season_reminders.manage')).toBe(false)
    expect(isApprovedRecipientPermission('')).toBe(false)
  })

  it('flags a stored permission that is no longer approved', () => {
    expect(hasUnapprovedRecipientPermission({ recipient_permission: 'roles.manage' })).toBe(true)
    expect(hasUnapprovedRecipientPermission({ recipient_permission: 'tasks.view' })).toBe(false)
  })

  it('has an Arabic label for every approved permission', () => {
    const labels = seasonalTemplatesAr.seasonalTemplates.reminders.recipients as Record<string, Record<string, string>>
    for (const permission of APPROVED_RECIPIENT_PERMISSIONS) {
      const [group, action] = permission.split('.')
      expect(labels[group!]?.[action!], permission).toBeTruthy()
    }
  })
})

describe('validateReminderRuleForm', () => {
  it('accepts a valid offset rule', () => {
    expect(validateReminderRuleForm(validForm(), { editing: false })).toEqual({})
  })

  it('accepts a valid absolute-date rule', () => {
    const form = validForm({ trigger_mode: 'absolute_date', trigger_on: '2027-05-20', offset_days: '' })
    expect(validateReminderRuleForm(form, { editing: false })).toEqual({})
  })

  it('requires code, title and recipient permission', () => {
    const errors = validateReminderRuleForm(validForm({ code: ' ', title: '', recipient_permission: '' }), { editing: false })
    expect(errors).toMatchObject({ code: 'required', title: 'required', recipient_permission: 'required' })
  })

  it('does not validate code when editing (immutable)', () => {
    expect(validateReminderRuleForm(validForm({ code: '' }), { editing: true })).toEqual({})
  })

  it('enforces max lengths', () => {
    const errors = validateReminderRuleForm(
      validForm({ code: 'x'.repeat(65), title: 'x'.repeat(256), body: 'x'.repeat(1001) }),
      { editing: false },
    )
    expect(errors).toMatchObject({ code: 'too_long', title: 'too_long', body: 'too_long' })
  })

  it('absolute mode requires a valid trigger_on and ignores offset_days', () => {
    expect(
      validateReminderRuleForm(validForm({ trigger_mode: 'absolute_date', trigger_on: '', offset_days: '5' }), { editing: false }),
    ).toEqual({ trigger_on: 'required' })
    expect(
      validateReminderRuleForm(validForm({ trigger_mode: 'absolute_date', trigger_on: '2027-02-31' }), { editing: false }),
    ).toEqual({ trigger_on: 'invalid_date' })
  })

  it('offset mode requires an integer within ±366 and ignores trigger_on', () => {
    expect(validateReminderRuleForm(validForm({ offset_days: '', trigger_on: '2027-01-01' }), { editing: false })).toEqual({
      offset_days: 'required',
    })
    expect(validateReminderRuleForm(validForm({ offset_days: '1.5' }), { editing: false }).offset_days).toBe('offset_not_integer')
    expect(validateReminderRuleForm(validForm({ offset_days: 'abc' }), { editing: false }).offset_days).toBe('offset_not_integer')
    expect(validateReminderRuleForm(validForm({ offset_days: '367' }), { editing: false }).offset_days).toBe('offset_out_of_range')
    expect(validateReminderRuleForm(validForm({ offset_days: '-367' }), { editing: false }).offset_days).toBe('offset_out_of_range')
    expect(validateReminderRuleForm(validForm({ offset_days: '366' }), { editing: false })).toEqual({})
    expect(validateReminderRuleForm(validForm({ offset_days: '0' }), { editing: false })).toEqual({})
  })

  it('rejects a recipient permission outside the allowlist', () => {
    expect(validateReminderRuleForm(validForm({ recipient_permission: 'users.manage' }), { editing: false })).toEqual({
      recipient_permission: 'permission_not_allowed',
    })
  })

  it('rejects unknown severity and trigger mode', () => {
    const errors = validateReminderRuleForm(
      validForm({ severity: 'urgent' as never, trigger_mode: 'weekly' as never }),
      { editing: false },
    )
    expect(errors).toMatchObject({ severity: 'invalid_choice', trigger_mode: 'invalid_choice' })
  })

  it('every produced error code has an Arabic message', () => {
    const messages = seasonalTemplatesAr.seasonalTemplates.reminders.validation as Record<string, string>
    for (const code of ['required', 'too_long', 'invalid_choice', 'invalid_date', 'offset_not_integer', 'offset_out_of_range', 'permission_not_allowed']) {
      expect(messages[code], code).toBeTruthy()
    }
  })
})

describe('payload builders', () => {
  it('create payload (offset) clears trigger_on, trims and never includes status', () => {
    const payload = buildCreatePayload(validForm({ code: ' A1 ', title: ' T ', body: '  ', trigger_on: '2027-01-01' }))
    expect(payload).toEqual({
      code: 'A1',
      title: 'T',
      body: null,
      severity: 'info',
      trigger_mode: 'offset_from_season_start',
      trigger_on: null,
      offset_days: -3,
      recipient_permission: 'tasks.view',
    })
    expect('status' in payload).toBe(false)
  })

  it('create payload (absolute) clears offset_days', () => {
    const payload = buildCreatePayload(validForm({ trigger_mode: 'absolute_date', trigger_on: '2027-05-20', offset_days: '9' }))
    expect(payload).toMatchObject({ trigger_mode: 'absolute_date', trigger_on: '2027-05-20', offset_days: null })
  })

  it('update payload never contains code or status, and resends the whole trigger', () => {
    const payload = buildUpdatePayload(validForm({ trigger_mode: 'absolute_date', trigger_on: '2027-05-20' }))
    expect('code' in payload).toBe(false)
    expect('status' in payload).toBe(false)
    expect(payload).toMatchObject({ trigger_mode: 'absolute_date', trigger_on: '2027-05-20', offset_days: null })
  })

  it('ruleToForm round-trips an absolute rule', () => {
    const form = ruleToForm(rule)
    expect(form).toMatchObject({ code: 'R-1', trigger_mode: 'absolute_date', trigger_on: '2027-05-20', offset_days: '', recipient_permission: 'meetings.view' })
  })

  it('ruleToForm round-trips an offset rule with negative days', () => {
    const form = ruleToForm({ ...rule, trigger_mode: 'offset_from_season_start', trigger_on: null, offset_days: -5, body: 'نص' })
    expect(form).toMatchObject({ trigger_mode: 'offset_from_season_start', trigger_on: '', offset_days: '-5', body: 'نص' })
  })
})

describe('fire date computation', () => {
  it('validates date-only strings', () => {
    expect(isValidDateOnly('2027-05-20')).toBe(true)
    expect(isValidDateOnly('2027-02-30')).toBe(false)
    expect(isValidDateOnly('27-05-20')).toBe(false)
  })

  it('adds days across month and year boundaries', () => {
    expect(addDaysToDate('2027-05-20', 12)).toBe('2027-06-01')
    expect(addDaysToDate('2027-01-02', -3)).toBe('2026-12-30')
    expect(addDaysToDate('bad', 1)).toBeNull()
  })

  it('absolute rules fire on trigger_on', () => {
    expect(computeFireDate({ trigger_mode: 'absolute_date', trigger_on: '2027-05-20', offset_days: null }, null)).toBe('2027-05-20')
  })

  it('offset rules fire on season start + offset', () => {
    expect(computeFireDate({ trigger_mode: 'offset_from_season_start', trigger_on: null, offset_days: -3 }, '2027-05-20')).toBe('2027-05-17')
    expect(computeFireDate({ trigger_mode: 'offset_from_season_start', trigger_on: null, offset_days: 0 }, '2027-05-20')).toBe('2027-05-20')
  })

  it('returns null when it cannot be computed', () => {
    expect(computeFireDate({ trigger_mode: 'offset_from_season_start', trigger_on: null, offset_days: 2 }, null)).toBeNull()
    expect(computeFireDate({ trigger_mode: 'offset_from_season_start', trigger_on: null, offset_days: null }, '2027-05-20')).toBeNull()
    expect(computeFireDate({ trigger_mode: 'absolute_date', trigger_on: null, offset_days: null }, '2027-05-20')).toBeNull()
  })

  it('computes from the text based form state', () => {
    expect(computeFormFireDate(validForm({ offset_days: '10' }), '2027-05-20')).toBe('2027-05-30')
    expect(computeFormFireDate(validForm({ offset_days: 'x' }), '2027-05-20')).toBeNull()
    expect(computeFormFireDate(validForm({ trigger_mode: 'absolute_date', trigger_on: '2027-06-01' }), null)).toBe('2027-06-01')
  })
})

describe('permission-gated actions (UX only)', () => {
  const manage = granted('season_reminders.manage')

  it('view and manage are separate permissions', () => {
    expect(canViewReminderRules(granted('season_reminders.view'))).toBe(true)
    expect(canManageReminderRules(granted('season_reminders.view'))).toBe(false)
    expect(canManageReminderRules(manage)).toBe(true)
  })

  it('hides every manage action without season_reminders.manage', () => {
    const viewOnly = granted('season_reminders.view')
    expect(canEditReminderRule('draft', false, viewOnly)).toBe(false)
    expect(canActivateReminderRule('draft', false, viewOnly)).toBe(false)
    expect(canPauseReminderRule('active', viewOnly)).toBe(false)
    expect(canArchiveReminderRule('active', viewOnly)).toBe(false)
  })

  it('edit only for draft/paused on a non-closed season', () => {
    expect(canEditReminderRule('draft', false, manage)).toBe(true)
    expect(canEditReminderRule('paused', false, manage)).toBe(true)
    expect(canEditReminderRule('active', false, manage)).toBe(false)
    expect(canEditReminderRule('archived', false, manage)).toBe(false)
    expect(canEditReminderRule('draft', true, manage)).toBe(false)
  })

  it('activate/resume for draft/paused, not on closed seasons', () => {
    expect(canActivateReminderRule('draft', false, manage)).toBe(true)
    expect(canActivateReminderRule('paused', false, manage)).toBe(true)
    expect(canActivateReminderRule('active', false, manage)).toBe(false)
    expect(canActivateReminderRule('archived', false, manage)).toBe(false)
    expect(canActivateReminderRule('draft', true, manage)).toBe(false)
  })

  it('pause only from active; archive from active/paused (also on closed seasons)', () => {
    expect(canPauseReminderRule('active', manage)).toBe(true)
    expect(canPauseReminderRule('paused', manage)).toBe(false)
    expect(canArchiveReminderRule('active', manage)).toBe(true)
    expect(canArchiveReminderRule('paused', manage)).toBe(true)
    expect(canArchiveReminderRule('draft', manage)).toBe(false)
    expect(canArchiveReminderRule('archived', manage)).toBe(false)
  })
})

describe('list state and error codes', () => {
  it('reports need_season before anything else', () => {
    expect(resolveReminderListState({ hasSeason: false, isLoading: true, isError: true, count: 0 })).toBe('need_season')
    expect(resolveReminderListState({ hasSeason: true, isLoading: true, isError: false, count: 0 })).toBe('loading')
    expect(resolveReminderListState({ hasSeason: true, isLoading: false, isError: true, count: 0 })).toBe('error')
    expect(resolveReminderListState({ hasSeason: true, isLoading: false, isError: false, count: 0 })).toBe('empty')
    expect(resolveReminderListState({ hasSeason: true, isLoading: false, isError: false, count: 2 })).toBe('ready')
  })

  it('maps known backend codes and falls back to generic', () => {
    expect(mapReminderErrorCode('SEASON_REMINDER_RULE_CODE_TAKEN')).toBe('SEASON_REMINDER_RULE_CODE_TAKEN')
    expect(mapReminderErrorCode('SOMETHING_ELSE')).toBe('generic')
    expect(mapReminderErrorCode(undefined)).toBe('generic')
  })

  it('has an Arabic message for every known code', () => {
    const errors = seasonalTemplatesAr.seasonalTemplates.reminders.errors as Record<string, string>
    for (const code of [
      'SEASON_REMINDER_RULE_INVALID_TRANSITION',
      'SEASON_REMINDER_RULE_NOT_EDITABLE',
      'SEASON_REMINDER_RULE_CODE_TAKEN',
      'SEASON_REMINDER_RULE_CODE_IMMUTABLE',
      'SEASON_REMINDER_RECIPIENT_PERMISSION_DENIED',
      'SEASON_REMINDER_TRIGGER_INVALID',
      'SEASON_REMINDER_SEASON_CLOSED',
      'VALIDATION_ERROR',
    ]) {
      expect(errors[code], code).toBeTruthy()
    }
  })
})
