import { describe, expect, it } from 'vitest'

import {
  mapEvaluationErrorCode,
  validateCreateEvaluation,
  weightedPreview,
} from './evaluationValidation'

describe('evaluationValidation', () => {
  it('maps known error codes', () => {
    expect(mapEvaluationErrorCode('EVALUATION_SOD_VIOLATION')).toBe('evaluations.errors.sod')
    expect(mapEvaluationErrorCode('UNKNOWN')).toBe('evaluations.errors.generic')
  })

  it('requires season for post_season', () => {
    const errors = validateCreateEvaluation({
      supplier_id: 1,
      supplier_category_id: 2,
      evaluation_kind: 'post_season',
    })
    expect(errors.season_id).toBe('required')
  })

  it('computes weighted preview', () => {
    const weights = new Map([
      [1, 60],
      [2, 40],
    ])
    expect(
      weightedPreview(
        [
          { criterion_id: 1, score: 100 },
          { criterion_id: 2, score: 50 },
        ],
        weights,
      ),
    ).toBe(80)
  })
})
