import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listDocuments } from '@/modules/documents/api/documentsApi'
import { documentsQueryKey } from '@/modules/documents/queries/useDocumentsQuery'

import { listSupplierDocumentRecords } from '../api/documentRecordsApi'
import { getEligibilitySet, listEligibilitySets } from '../api/eligibilitySetsApi'
import { getSupplierEligibility } from '../api/suppliersApi'
import type {
  EligibilityRequirementDef,
  ListEligibilitySetsParams,
} from '../types/eligibility'

export const eligibilitySetsQueryKey = ['suppliers', 'eligibility-sets'] as const

export function supplierEligibilityQueryKey(supplierId: number) {
  return ['suppliers', 'eligibility', supplierId] as const
}

export function supplierDocumentRecordsQueryKey(supplierId: number) {
  return ['suppliers', 'document-records', supplierId] as const
}

export function useEligibilitySetsQuery(
  params: MaybeRefOrGetter<ListEligibilitySetsParams>,
  options: { enabled?: MaybeRefOrGetter<boolean> } = {},
) {
  return useQuery({
    queryKey: computed(() => [...eligibilitySetsQueryKey, 'list', toValue(params)]),
    queryFn: () => listEligibilitySets(toValue(params)),
    placeholderData: keepPreviousData,
    enabled: computed(() => (options.enabled === undefined ? true : toValue(options.enabled))),
  })
}

export function useEligibilitySetQuery(
  id: MaybeRefOrGetter<number | null>,
  options: { enabled?: MaybeRefOrGetter<boolean> } = {},
) {
  return useQuery({
    queryKey: computed(() => [...eligibilitySetsQueryKey, 'detail', toValue(id)]),
    queryFn: () => {
      const value = toValue(id)
      if (value == null) throw new Error('missing eligibility set id')
      return getEligibilitySet(value)
    },
    enabled: computed(
      () => toValue(id) != null && (options.enabled === undefined ? true : toValue(options.enabled)),
    ),
  })
}

export function useSupplierEligibilityQuery(
  supplierId: MaybeRefOrGetter<number | null>,
  options: { enabled?: MaybeRefOrGetter<boolean> } = {},
) {
  return useQuery({
    queryKey: computed(() => {
      const id = toValue(supplierId)
      return id != null ? supplierEligibilityQueryKey(id) : ['suppliers', 'eligibility', 'none']
    }),
    queryFn: () => {
      const id = toValue(supplierId)
      if (id == null) throw new Error('missing supplier id')
      return getSupplierEligibility(id)
    },
    enabled: computed(
      () =>
        toValue(supplierId) != null &&
        (options.enabled === undefined ? true : toValue(options.enabled)),
    ),
  })
}

export function useSupplierDocumentRecordsQuery(
  supplierId: MaybeRefOrGetter<number | null>,
  options: { enabled?: MaybeRefOrGetter<boolean> } = {},
) {
  return useQuery({
    queryKey: computed(() => {
      const id = toValue(supplierId)
      return id != null
        ? supplierDocumentRecordsQueryKey(id)
        : ['suppliers', 'document-records', 'none']
    }),
    queryFn: () => {
      const id = toValue(supplierId)
      if (id == null) throw new Error('missing supplier id')
      return listSupplierDocumentRecords(id)
    },
    enabled: computed(
      () =>
        toValue(supplierId) != null &&
        (options.enabled === undefined ? true : toValue(options.enabled)),
    ),
  })
}

/** Documents (Documents module) already uploaded and linked to this supplier. */
export function useSupplierLinkedDocumentsQuery(
  supplierId: MaybeRefOrGetter<number | null>,
  options: { enabled?: MaybeRefOrGetter<boolean> } = {},
) {
  return useQuery({
    queryKey: computed(() => [...documentsQueryKey, 'list', 'supplier-link', toValue(supplierId)]),
    queryFn: async () => {
      const id = toValue(supplierId)
      if (id == null) throw new Error('missing supplier id')
      const result = await listDocuments({
        linkable_type: 'supplier',
        linkable_id: id,
        status: 'active',
        per_page: 100,
      })
      return result.data
    },
    enabled: computed(
      () =>
        toValue(supplierId) != null &&
        (options.enabled === undefined ? true : toValue(options.enabled)),
    ),
  })
}

/**
 * Loads requirement defs of the approved config versions that contributed to the
 * eligibility evaluation, so the checklist can map requirement_key → requirement_def_id.
 */
export function useEligibilityDefsQuery(
  configIds: MaybeRefOrGetter<number[]>,
  options: { enabled?: MaybeRefOrGetter<boolean> } = {},
) {
  return useQuery({
    queryKey: computed(() => [...eligibilitySetsQueryKey, 'defs', [...toValue(configIds)].sort()]),
    queryFn: async (): Promise<EligibilityRequirementDef[]> => {
      const sets = await Promise.all(toValue(configIds).map((id) => getEligibilitySet(id)))
      return sets.flatMap((set) => set.requirement_defs ?? [])
    },
    enabled: computed(
      () =>
        toValue(configIds).length > 0 &&
        (options.enabled === undefined ? true : toValue(options.enabled)),
    ),
  })
}
