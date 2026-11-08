import { describe, expect, it } from 'vitest'

import {
  buildContractSnapshotRows,
  emptyContractRow,
  hasContractRows,
  validateContractRows,
} from './contractDefinitionRows'
import { buildTemplateItems, emptyTemplateForm, validateTemplateForm } from './seasonTemplateValidation'

describe('contract definition row editor validation', () => {
  it('accepts a valid row set and builds an allowlisted snapshot', () => {
    const rows = [
      {
        ...emptyContractRow(),
        code: 'CTR-A',
        title: 'عقد نقل',
        contract_category_id: 7,
        counterparty_kind: 'organization' as const,
        currency: 'sar',
        start_date: '2027-05-10',
        end_date: '2027-06-10',
        notes: 'ملاحظة',
      },
    ]
    expect(validateContractRows(rows, '2027-05-01').hasErrors).toBe(false)
    expect(buildContractSnapshotRows(rows)).toEqual([
      {
        code: 'CTR-A',
        title: 'عقد نقل',
        contract_category_id: 7,
        counterparty_kind: 'organization',
        currency: 'SAR',
        start_date: '2027-05-10',
        end_date: '2027-06-10',
        notes: 'ملاحظة',
      },
    ])
  })

  it('rejects missing title/category/start, bad currency, end before start, and duplicate codes', () => {
    const rows = [
      { ...emptyContractRow(), code: 'A', title: '', contract_category_id: '', start_date: '' },
      {
        ...emptyContractRow(),
        code: 'a',
        title: 'ثاني',
        contract_category_id: 1,
        start_date: '2027-06-01',
        end_date: '2027-05-01',
        currency: 'SA',
      },
    ]
    const result = validateContractRows(rows, '')
    expect(result.hasErrors).toBe(true)
    expect(result.anchor).toBe('required')
    expect(result.rows[0].title).toBe('required')
    expect(result.rows[0].contract_category_id).toBe('required')
    expect(result.rows[0].start_date).toBe('required')
    expect(result.rows[0].code).toBe('duplicate_code')
    expect(result.rows[1].code).toBe('duplicate_code')
    expect(result.rows[1].end_date).toBe('end_before_start')
    expect(result.rows[1].currency).toBe('currency_invalid')
  })

  it('never puts denied financial or counterparty fields into the snapshot payload', () => {
    const form = {
      ...emptyTemplateForm(),
      name: 'قالب عقود',
      entity_kinds: ['contract_definitions'],
      contract_anchor: '2027-05-01',
      contract_rows: [
        {
          ...emptyContractRow(),
          title: 'عقد',
          contract_category_id: 3,
          start_date: '2027-05-10',
        },
      ],
    }
    expect(validateTemplateForm(form)).toEqual({})
    expect(hasContractRows(form.contract_rows)).toBe(true)
    const items = buildTemplateItems(form)
    expect(items).toHaveLength(1)
    expect(items[0].entity_kind).toBe('contract_definitions')
    const payload = JSON.stringify(items[0].snapshot)
    expect(payload).not.toContain('counterparty_name')
    expect(payload).not.toContain('value')
    expect(payload).not.toContain('employee_id')
    expect(payload).not.toContain('supplier_id')
    expect(payload).not.toContain('organization_unit_id')
  })
})
