import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useQuery } from '@tanstack/vue-query'

import {
  getSupplierEvaluation,
  listEvaluationAppeals,
  listSupplierEvaluations,
} from '../api/evaluationsApi'
import type { EvaluationListFilters } from '../types/evaluations'

export function useEvaluationsQuery(filters: MaybeRefOrGetter<EvaluationListFilters>) {
  return useQuery({
    queryKey: computed(() => ['supplier-evaluations', toValue(filters)]),
    queryFn: () => listSupplierEvaluations(toValue(filters)),
  })
}

export function useEvaluationQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => ['supplier-evaluation', toValue(id)]),
    queryFn: () => getSupplierEvaluation(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && value > 0
    }),
  })
}

export function useEvaluationAppealsQuery(evaluationId: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => ['supplier-evaluation-appeals', toValue(evaluationId)]),
    queryFn: () => listEvaluationAppeals(toValue(evaluationId)!),
    enabled: computed(() => {
      const value = toValue(evaluationId)
      return value != null && value > 0
    }),
  })
}
