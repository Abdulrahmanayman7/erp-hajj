import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { approveDecision, cancelDecision, closeDecision, createDecision, deleteDecision, returnDecisionToDraft, submitDecision, updateDecision } from '../api/decisionsApi'
import { decisionDetailQueryKey, decisionsQueryKey } from '../queries/useDecisionsQuery'
import type { DecisionPayload } from '../types/decisions'

async function invalidate(client: ReturnType<typeof useQueryClient>, id?: number) {
  await client.invalidateQueries({ queryKey: decisionsQueryKey })
  if (id) await client.invalidateQueries({ queryKey: decisionDetailQueryKey(id) })
}
export function useCreateDecisionMutation() { const client = useQueryClient(); return useMutation({ mutationFn: createDecision, onSuccess: () => invalidate(client) }) }
export function useUpdateDecisionMutation() { const client = useQueryClient(); return useMutation({ mutationFn: ({ id, payload }: { id: number; payload: DecisionPayload }) => updateDecision(id, payload), onSuccess: (_, v) => invalidate(client, v.id) }) }
export function useDeleteDecisionMutation() { const client = useQueryClient(); return useMutation({ mutationFn: deleteDecision, onSuccess: (_, id) => invalidate(client, id) }) }
function transition(name: 'submit' | 'return' | 'approve' | 'cancel' | 'close') {
  const client = useQueryClient()
  return useMutation({ mutationFn: ({ id, comment }: { id: number; comment?: string }) => ({ submit: submitDecision, return: (v: number) => returnDecisionToDraft(v, comment ?? ''), approve: approveDecision, cancel: cancelDecision, close: closeDecision }[name])(id), onSuccess: (_, v) => invalidate(client, v.id) })
}
export const useSubmitDecisionMutation = () => transition('submit')
export const useReturnDecisionDraftMutation = () => transition('return')
export const useApproveDecisionMutation = () => transition('approve')
export const useCancelDecisionMutation = () => transition('cancel')
export const useCloseDecisionMutation = () => transition('close')
