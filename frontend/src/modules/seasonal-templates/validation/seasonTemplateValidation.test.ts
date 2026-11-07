import { describe, expect, it } from 'vitest'

import { APPROVED_ENTITY_KINDS } from '../types/seasonCopy'
import {
  buildTemplateItems,
  canApproveTemplate,
  canArchiveTemplate,
  canEditTemplate,
  canShowCreateTemplate,
  canShowTemplateSource,
  canViewTemplates,
  deniedEntityKinds,
  emptyTemplateForm,
  filterApprovedEntityKinds,
  isApprovedEntityKind,
  mapSeasonalTemplatesErrorCode,
  parseCodes,
  resolveListState,
  templateToForm,
  validateTemplateForm,
} from './seasonTemplateValidation'

const granted = (...permissions: string[]) => (permission: string) => permissions.includes(permission)

describe('approved entity kinds', () => {
  it('exposes exactly the five approved kinds', () => {
    expect([...APPROVED_ENTITY_KINDS]).toEqual([
      'season_programs',
      'season_pilgrim_groups',
      'transport_entitlements',
      'transport_season_vehicles',
      'accommodation_season_facilities',
    ])
  })

  it('denies unapproved kinds', () => {
    expect(isApprovedEntityKind('season_programs')).toBe(true)
    expect(isApprovedEntityKind('season_pilgrims')).toBe(false)
    expect(isApprovedEntityKind('finance_ledger')).toBe(false)
    expect(isApprovedEntityKind('')).toBe(false)
    expect(deniedEntityKinds(['season_programs', 'pilgrims', 'contracts'])).toEqual(['pilgrims', 'contracts'])
  })

  it('filters to approved kinds in canonical order without duplicates', () => {
    expect(
      filterApprovedEntityKinds(['transport_entitlements', 'bogus', 'season_programs', 'season_programs']),
    ).toEqual(['season_programs', 'transport_entitlements'])
  })
})

describe('validateTemplateForm', () => {
  it('requires name and at least one kind', () => {
    expect(validateTemplateForm(emptyTemplateForm())).toEqual({
      name: 'required',
      entity_kinds: 'required',
    })
  })

  it('passes with a name and an approved kind', () => {
    expect(
      validateTemplateForm({ ...emptyTemplateForm(), name: 'قالب', entity_kinds: ['season_programs'] }),
    ).toEqual({})
  })

  it('rejects a denied entity kind', () => {
    expect(
      validateTemplateForm({ ...emptyTemplateForm(), name: 'قالب', entity_kinds: ['season_programs', 'pilgrims'] }),
    ).toEqual({ entity_kinds: 'denied' })
  })

  it('rejects overlong name and description', () => {
    const errors = validateTemplateForm({
      name: 'ا'.repeat(201),
      description: 'ب'.repeat(5001),
      entity_kinds: ['season_programs'],
      codes: {},
    })
    expect(errors).toEqual({ name: 'too_long', description: 'too_long' })
  })
})

describe('template items payload', () => {
  it('never sends unapproved kinds and omits empty selections', () => {
    const items = buildTemplateItems({
      name: 'x',
      description: '',
      entity_kinds: ['transport_entitlements', 'pilgrims', 'season_programs'],
      codes: { season_programs: 'A, B\nA', pilgrims: 'X' },
    })
    expect(items).toEqual([
      { entity_kind: 'season_programs', selection: { codes: ['A', 'B'] } },
      { entity_kind: 'transport_entitlements' },
    ])
  })

  it('parses codes on newlines and commas (including Arabic comma)', () => {
    expect(parseCodes('a,b\nc، d\n\n')).toEqual(['a', 'b', 'c', 'd'])
    expect(parseCodes(undefined)).toEqual([])
  })

  it('round-trips a template into form state with approved kinds only', () => {
    const form = templateToForm({
      id: 1,
      template_number: 'T-1',
      name: 'قالب',
      description: null,
      status: 'draft',
      matrix_version: 'v',
      items: [
        { entity_kind: 'season_programs', sort_order: 0, selection: { codes: ['P1', 'P2'] } },
        { entity_kind: 'legacy_kind', sort_order: 1, selection: null },
      ],
      approved_at: null,
      archived_at: null,
      created_at: null,
      updated_at: null,
      created_by: null,
      approved_by: null,
    })
    expect(form.entity_kinds).toEqual(['season_programs'])
    expect(form.codes.season_programs).toBe('P1\nP2')
  })
})

describe('permission visibility (UX only)', () => {
  it('shows the create CTA only with season_templates.manage', () => {
    expect(canShowCreateTemplate(granted('season_templates.manage'))).toBe(true)
    expect(canShowCreateTemplate(granted('season_templates.view'))).toBe(false)
    expect(canShowCreateTemplate(granted())).toBe(false)
  })

  it('requires view permission to view templates and use them as copy source', () => {
    expect(canViewTemplates(granted('season_templates.view'))).toBe(true)
    expect(canViewTemplates(granted('season_templates.manage'))).toBe(false)
    expect(canShowTemplateSource(granted('season_templates.view'))).toBe(true)
    expect(canShowTemplateSource(granted('season_copy.preview'))).toBe(false)
  })

  it('allows editing drafts only, and only with manage', () => {
    const manage = granted('season_templates.manage')
    expect(canEditTemplate('draft', manage)).toBe(true)
    expect(canEditTemplate('approved', manage)).toBe(false)
    expect(canEditTemplate('archived', manage)).toBe(false)
    expect(canEditTemplate('draft', granted('season_templates.view'))).toBe(false)
  })

  it('allows approving non-empty drafts with manage only', () => {
    const manage = granted('season_templates.manage')
    const item = { entity_kind: 'season_programs', sort_order: 0, selection: null }
    expect(canApproveTemplate({ status: 'draft', items: [item] }, manage)).toBe(true)
    expect(canApproveTemplate({ status: 'draft', items: [] }, manage)).toBe(false)
    expect(canApproveTemplate({ status: 'approved', items: [item] }, manage)).toBe(false)
    expect(canApproveTemplate({ status: 'draft', items: [item] }, granted())).toBe(false)
  })

  it('allows archiving draft or approved templates with manage only', () => {
    const manage = granted('season_templates.manage')
    expect(canArchiveTemplate('draft', manage)).toBe(true)
    expect(canArchiveTemplate('approved', manage)).toBe(true)
    expect(canArchiveTemplate('archived', manage)).toBe(false)
    expect(canArchiveTemplate('approved', granted('season_templates.view'))).toBe(false)
  })
})

describe('list state and error mapping', () => {
  it('resolves loading before error before empty', () => {
    expect(resolveListState({ isLoading: true, isError: true, count: 0 })).toBe('loading')
    expect(resolveListState({ isLoading: false, isError: true, count: 0 })).toBe('error')
    expect(resolveListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
    expect(resolveListState({ isLoading: false, isError: false, count: 2 })).toBe('ready')
  })

  it('maps known backend codes and falls back to generic', () => {
    expect(mapSeasonalTemplatesErrorCode('SEASON_COPY_PREVIEW_STALE')).toBe('SEASON_COPY_PREVIEW_STALE')
    expect(mapSeasonalTemplatesErrorCode('SEASON_COPY_KIND_DENIED')).toBe('SEASON_COPY_KIND_DENIED')
    expect(mapSeasonalTemplatesErrorCode('SOMETHING_ELSE')).toBe('generic')
    expect(mapSeasonalTemplatesErrorCode(undefined)).toBe('generic')
  })
})
