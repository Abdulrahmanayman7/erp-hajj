import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useQuery } from '@tanstack/vue-query'

import {
  getDuplicateHints,
  getPurchaseRequest,
  listApprovalInbox,
  listFinancialReviews,
  listPurchaseApprovalDelegations,
  listPurchaseApprovalMatrices,
  listPurchaseApprovalSlaPolicies,
  listPurchaseApproverAuthorities,
  listPurchaseRequestVersions,
  listPurchaseRequests,
} from '../api/purchaseRequestsApi'
import type { PurchaseRequestListFilters } from '../types/purchaseRequests'

export function usePurchaseRequestsQuery(filters: MaybeRefOrGetter<PurchaseRequestListFilters>) {
  return useQuery({
    queryKey: computed(() => ['purchase-requests', toValue(filters)]),
    queryFn: () => listPurchaseRequests(toValue(filters)),
  })
}

export function usePurchaseRequestQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => ['purchase-request', toValue(id)]),
    queryFn: () => getPurchaseRequest(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && value > 0
    }),
  })
}

export function usePurchaseRequestVersionsQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => ['purchase-request-versions', toValue(id)]),
    queryFn: () => listPurchaseRequestVersions(toValue(id)!, true),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && value > 0
    }),
  })
}

export function usePurchaseRequestFinancialReviewsQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => ['purchase-request-financial-reviews', toValue(id)]),
    queryFn: () => listFinancialReviews(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && value > 0
    }),
  })
}

export function useDuplicateHintsQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => ['purchase-request-duplicate-hints', toValue(id)]),
    queryFn: () => getDuplicateHints(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && value > 0
    }),
  })
}

export function usePurchaseRequestApprovalInboxQuery(enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: ['purchase-request-approval-inbox'],
    queryFn: () => listApprovalInbox(),
    enabled: computed(() => toValue(enabled)),
  })
}

export function usePurchaseApprovalMatricesQuery(enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: ['purchase-approval-matrices'],
    queryFn: () => listPurchaseApprovalMatrices(),
    enabled: computed(() => toValue(enabled)),
  })
}

export function usePurchaseApproverAuthoritiesQuery(enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: ['purchase-approver-authorities'],
    queryFn: () => listPurchaseApproverAuthorities(),
    enabled: computed(() => toValue(enabled)),
  })
}

export function usePurchaseApprovalDelegationsQuery(enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: ['purchase-approval-delegations'],
    queryFn: () => listPurchaseApprovalDelegations(),
    enabled: computed(() => toValue(enabled)),
  })
}

export function usePurchaseApprovalSlaPoliciesQuery(enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: ['purchase-approval-sla-policies'],
    queryFn: () => listPurchaseApprovalSlaPolicies(),
    enabled: computed(() => toValue(enabled)),
  })
}
