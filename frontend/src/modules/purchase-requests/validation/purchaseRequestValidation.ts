import type {
  CreatePurchaseRequestPayload,
  PurchaseRequestPriority,
} from '../types/purchaseRequests'

export function mapPurchaseRequestErrorCode(code: string | undefined): string {
  switch (code) {
    case 'PR_EDIT_LOCKED':
      return 'purchaseRequests.errors.editLocked'
    case 'PR_INVALID_TRANSITION':
      return 'purchaseRequests.errors.invalidTransition'
    case 'PR_DUPLICATE_ACK_REQUIRED':
      return 'purchaseRequests.errors.duplicateAck'
    case 'PR_LINES_REQUIRED':
      return 'purchaseRequests.errors.linesRequired'
    case 'PR_UNPRICED_REASON_REQUIRED':
      return 'purchaseRequests.errors.unpricedReason'
    case 'PR_URGENCY_JUSTIFICATION_REQUIRED':
      return 'purchaseRequests.errors.urgency'
    case 'PR_SEASON_REQUIRED':
      return 'purchaseRequests.errors.seasonRequired'
    case 'PR_SUPPLIER_NOT_ELIGIBLE':
      return 'purchaseRequests.errors.supplierIneligible'
    case 'PR_SUPPLIER_ARCHIVED':
      return 'purchaseRequests.errors.supplierArchived'
    case 'PR_FINANCIAL_REVIEW_VERSION_MISMATCH':
      return 'purchaseRequests.errors.versionMismatch'
    case 'PR_CANCEL_REASON_REQUIRED':
      return 'purchaseRequests.errors.cancelReason'
    case 'PR_JUSTIFICATION_TOO_SHORT':
      return 'purchaseRequests.errors.justificationShort'
    case 'PR_PROCUREMENT_ENGAGED':
      return 'purchaseRequests.errors.procurementEngaged'
    default:
      return 'purchaseRequests.errors.generic'
  }
}

export function priorityNeedsJustification(priority: PurchaseRequestPriority): boolean {
  return priority === 'urgent' || priority === 'emergency'
}

export function validateCreatePurchaseRequest(
  payload: CreatePurchaseRequestPayload,
): Record<string, string> {
  const errors: Record<string, string> = {}

  if (!payload.title?.trim()) errors.title = 'required'
  if (!payload.purpose?.trim()) errors.purpose = 'required'
  if (!payload.beneficiary_organization_unit_id) errors.beneficiary_organization_unit_id = 'required'
  if (payload.request_kind === 'seasonal' && !payload.season_id) errors.season_id = 'required'
  if (priorityNeedsJustification(payload.priority) && !payload.urgency_justification?.trim()) {
    errors.urgency_justification = 'required'
  }
  if (!payload.lines?.length) {
    errors.lines = 'required'
  } else {
    payload.lines.forEach((line, index) => {
      if (!line.description?.trim()) errors[`lines.${index}.description`] = 'required'
      if (!(Number(line.quantity) > 0)) errors[`lines.${index}.quantity`] = 'required'
      if (!line.unit?.trim()) errors[`lines.${index}.unit`] = 'required'
      if (!line.need_date) errors[`lines.${index}.need_date`] = 'required'
      const priced =
        line.unit_estimate != null && line.unit_estimate !== undefined
          ? Number(line.unit_estimate) >= 0
          : line.line_estimate != null && Number(line.line_estimate) >= 0
      if (!priced && !line.unpriced_reason?.trim()) {
        errors[`lines.${index}.unpriced_reason`] = 'required'
      }
    })
  }

  return errors
}

export const requestKindOptions = [
  { value: 'administrative', labelKey: 'purchaseRequests.kinds.administrative' },
  { value: 'seasonal', labelKey: 'purchaseRequests.kinds.seasonal' },
] as const

export const priorityOptions = [
  { value: 'normal', labelKey: 'purchaseRequests.priorities.normal' },
  { value: 'urgent', labelKey: 'purchaseRequests.priorities.urgent' },
  { value: 'emergency', labelKey: 'purchaseRequests.priorities.emergency' },
] as const

export const statusOptions = [
  { value: 'draft', labelKey: 'purchaseRequests.statuses.draft' },
  { value: 'submitted', labelKey: 'purchaseRequests.statuses.submitted' },
  { value: 'cancelled', labelKey: 'purchaseRequests.statuses.cancelled' },
  { value: 'returned', labelKey: 'purchaseRequests.statuses.returned' },
  { value: 'under_review', labelKey: 'purchaseRequests.statuses.underReview' },
  { value: 'approved', labelKey: 'purchaseRequests.statuses.approved' },
  { value: 'rejected', labelKey: 'purchaseRequests.statuses.rejected' },
] as const
