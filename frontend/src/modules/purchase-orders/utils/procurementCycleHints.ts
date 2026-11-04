export type CycleEntity =
  | { kind: 'pr'; status: string; engaged: boolean }
  | { kind: 'rfq'; status: string }
  | { kind: 'po'; status: string; remaining?: string | number | null }
  | { kind: 'grn'; status: string }

export interface CycleHint {
  key: string
  labelKey: string
}

/**
 * Lightweight next-step hints for S05 detail screens (permission gating stays in the page).
 */
export function nextCycleHint(entity: CycleEntity): CycleHint | null {
  switch (entity.kind) {
    case 'pr':
      if (entity.status === 'approved' && !entity.engaged) {
        return { key: 'create_rfq', labelKey: 'procurementCycle.next.createRfq' }
      }
      if (entity.engaged) {
        return { key: 'view_rfq', labelKey: 'procurementCycle.next.viewRfq' }
      }
      return null
    case 'rfq':
      if (entity.status === 'draft') {
        return { key: 'publish', labelKey: 'procurementCycle.next.publishRfq' }
      }
      if (entity.status === 'published') {
        return { key: 'invite_or_select', labelKey: 'procurementCycle.next.inviteOrSelect' }
      }
      if (entity.status === 'awarded') {
        return { key: 'create_po', labelKey: 'procurementCycle.next.createPo' }
      }
      return null
    case 'po':
      if (entity.status === 'draft') {
        return { key: 'issue', labelKey: 'procurementCycle.next.issuePo' }
      }
      if (entity.status === 'issued' || entity.status === 'partially_received') {
        return { key: 'receive', labelKey: 'procurementCycle.next.createGrn' }
      }
      if (entity.status === 'received') {
        return { key: 'close', labelKey: 'procurementCycle.next.closePo' }
      }
      if (entity.status === 'closed') {
        return { key: 'done', labelKey: 'procurementCycle.next.cycleClosed' }
      }
      return null
    case 'grn':
      if (entity.status === 'draft') {
        return { key: 'post', labelKey: 'procurementCycle.next.postGrn' }
      }
      if (entity.status === 'posted') {
        return { key: 'back_po', labelKey: 'procurementCycle.next.backToPo' }
      }
      return null
    default:
      return null
  }
}
