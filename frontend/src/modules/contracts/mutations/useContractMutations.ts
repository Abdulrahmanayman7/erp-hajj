import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  approveContract,
  cancelContract,
  closeContract,
  createContract,
  deleteContract,
  executeContract,
  renewContract,
  returnContractToDraft,
  signContract,
  submitContractForReview,
  updateContract,
} from '../api/contractsApi'
import { contractDetailQueryKey } from '../queries/useContractQuery'
import { contractsQueryKey } from '../queries/useContractsQuery'
import type {
  ContractTransitionPayload,
  CreateContractPayload,
  UpdateContractPayload,
} from '../types/contracts'

async function invalidateContractQueries(
  queryClient: ReturnType<typeof useQueryClient>,
  id?: number,
): Promise<void> {
  await queryClient.invalidateQueries({ queryKey: contractsQueryKey })
  if (id != null) {
    await queryClient.invalidateQueries({ queryKey: contractDetailQueryKey(id) })
  }
}

export function useCreateContractMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateContractPayload) => createContract(payload),
    onSuccess: async () => {
      await invalidateContractQueries(queryClient)
    },
  })
}

export function useUpdateContractMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateContractPayload }) =>
      updateContract(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateContractQueries(queryClient, variables.id)
    },
  })
}

export function useDeleteContractMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteContract(id),
    onSuccess: async (_data, id) => {
      await invalidateContractQueries(queryClient, id)
    },
  })
}

export function useSubmitContractReviewMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: ContractTransitionPayload }) =>
      submitContractForReview(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateContractQueries(queryClient, variables.id)
    },
  })
}

export function useReturnContractDraftMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: ContractTransitionPayload }) =>
      returnContractToDraft(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateContractQueries(queryClient, variables.id)
    },
  })
}

export function useApproveContractMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: ContractTransitionPayload }) =>
      approveContract(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateContractQueries(queryClient, variables.id)
    },
  })
}

export function useSignContractMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: ContractTransitionPayload }) =>
      signContract(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateContractQueries(queryClient, variables.id)
    },
  })
}

export function useExecuteContractMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: ContractTransitionPayload }) =>
      executeContract(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateContractQueries(queryClient, variables.id)
    },
  })
}

export function useCloseContractMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: ContractTransitionPayload }) =>
      closeContract(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateContractQueries(queryClient, variables.id)
    },
  })
}

export function useCancelContractMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: ContractTransitionPayload }) =>
      cancelContract(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateContractQueries(queryClient, variables.id)
    },
  })
}

export function useRenewContractMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: ContractTransitionPayload }) =>
      renewContract(id, payload),
    onSuccess: async (result, variables) => {
      await invalidateContractQueries(queryClient, variables.id)
      await queryClient.invalidateQueries({
        queryKey: contractDetailQueryKey(result.successor.id),
      })
    },
  })
}
