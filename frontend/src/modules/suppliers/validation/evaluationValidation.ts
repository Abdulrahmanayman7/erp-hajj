import type { CreateEvaluationPayload, EvaluationDraftScore, EvaluationKind } from '../types/evaluations'

export function mapEvaluationErrorCode(code: string | undefined): string {
  switch (code) {
    case 'EVALUATION_SOD_VIOLATION':
      return 'evaluations.errors.sod'
    case 'EVALUATION_SEASON_REQUIRED':
      return 'evaluations.errors.seasonRequired'
    case 'EVALUATION_CRITERIA_MISSING':
      return 'evaluations.errors.criteriaMissing'
    case 'EVALUATION_SUPPLIER_ARCHIVED':
      return 'evaluations.errors.supplierArchived'
    case 'EVALUATION_CATEGORY_NOT_LINKED':
      return 'evaluations.errors.categoryNotLinked'
    case 'EVALUATION_INVALID_STATUS':
      return 'evaluations.errors.invalidStatus'
    case 'EVALUATION_APPEAL_SOD_VIOLATION':
      return 'evaluations.errors.appealSod'
    case 'EVALUATION_APPEAL_EXISTS':
      return 'evaluations.errors.appealExists'
    default:
      return 'evaluations.errors.generic'
  }
}

export function weightedPreview(scores: EvaluationDraftScore[], weights: Map<number, number>): number {
  let total = 0
  for (const row of scores) {
    const weight = weights.get(row.criterion_id) ?? 0
    const score = Number(row.score)
    if (!Number.isFinite(score)) continue
    total += score * (weight / 100)
  }
  return Math.round(total * 100) / 100
}

export function validateCreateEvaluation(payload: CreateEvaluationPayload): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!payload.supplier_id) errors.supplier_id = 'required'
  if (!payload.supplier_category_id) errors.supplier_category_id = 'required'
  if (!payload.evaluation_kind) errors.evaluation_kind = 'required'
  if (payload.evaluation_kind === 'post_season' && !payload.season_id) {
    errors.season_id = 'required'
  }
  if (payload.scores) {
    for (const row of payload.scores) {
      const score = Number(row.score)
      if (!Number.isFinite(score) || score < 0 || score > 100) {
        errors[`score_${row.criterion_id}`] = 'range'
      }
    }
  }
  return errors
}

export const evaluationKindOptions: Array<{ value: EvaluationKind; labelKey: string }> = [
  { value: 'qualification', labelKey: 'evaluations.kinds.qualification' },
  { value: 'periodic', labelKey: 'evaluations.kinds.periodic' },
  { value: 'operational', labelKey: 'evaluations.kinds.operational' },
  { value: 'post_season', labelKey: 'evaluations.kinds.post_season' },
]
