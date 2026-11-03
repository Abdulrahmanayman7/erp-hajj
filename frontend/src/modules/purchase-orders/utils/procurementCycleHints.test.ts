import { describe, expect, it } from 'vitest'

import { nextCycleHint } from './procurementCycleHints'

describe('nextCycleHint', () => {
  it('suggests RFQ create for approved unengaged PR', () => {
    expect(nextCycleHint({ kind: 'pr', status: 'approved', engaged: false })?.key).toBe('create_rfq')
  })

  it('suggests PO create after RFQ award and receive after issue', () => {
    expect(nextCycleHint({ kind: 'rfq', status: 'awarded' })?.key).toBe('create_po')
    expect(nextCycleHint({ kind: 'po', status: 'issued' })?.key).toBe('receive')
    expect(nextCycleHint({ kind: 'po', status: 'received' })?.key).toBe('close')
    expect(nextCycleHint({ kind: 'po', status: 'closed' })?.key).toBe('done')
  })

  it('suggests GRN post for draft receipts', () => {
    expect(nextCycleHint({ kind: 'grn', status: 'draft' })?.key).toBe('post')
  })
})
