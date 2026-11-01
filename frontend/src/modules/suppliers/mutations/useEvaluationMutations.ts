import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  acceptEvaluationAppeal,
  approveSupplierEvaluation,
  createSupplierEvaluation,
  fileEvaluationAppeal,
  rejectEvaluationAppeal,
  rejectSupplierEvaluation,
  submitSupplierEvaluation,
  updateSupplierEvaluation,
} from '../api/evaluationsApi'
import type { CreateEvaluationPayload, UpdateEvaluationPayload } from '../types/evaluations'

export function useCreateEvaluationMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateEvaluationPayload) => createSupplierEvaluation(payload),
    onSuccess: async () => {
      await qc.invalidateQueries({ queryKey: ['supplier-evaluations'] })
    },
  })
}

export function useUpdateEvaluationMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateEvaluationPayload }) =>
      updateSupplierEvaluation(id, payload),
    onSuccess: async (data) => {
      await qc.invalidateQueries({ queryKey: ['supplier-evaluations'] })
      await qc.invalidateQueries({ queryKey: ['supplier-evaluation', data.id] })
    },
  })
}

export function useSubmitEvaluationMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => submitSupplierEvaluation(id),
    onSuccess: async (data) => {
      await qc.invalidateQueries({ queryKey: ['supplier-evaluations'] })
      await qc.invalidateQueries({ queryKey: ['supplier-evaluation', data.id] })
    },
  })
}

export function useApproveEvaluationMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, monitoring }: { id: number; monitoring?: boolean }) =>
      approveSupplierEvaluation(id, monitoring),
    onSuccess: async (data) => {
      await qc.invalidateQueries({ queryKey: ['supplier-evaluations'] })
      await qc.invalidateQueries({ queryKey: ['supplier-evaluation', data.id] })
    },
  })
}

export function useRejectEvaluationMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      rejectSupplierEvaluation(id, reason),
    onSuccess: async (data) => {
      await qc.invalidateQueries({ queryKey: ['supplier-evaluations'] })
      await qc.invalidateQueries({ queryKey: ['supplier-evaluation', data.id] })
    },
  })
}

export function useFileAppealMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ evaluationId, reason }: { evaluationId: number; reason: string }) =>
      fileEvaluationAppeal(evaluationId, reason),
    onSuccess: async (_data, vars) => {
      await qc.invalidateQueries({ queryKey: ['supplier-evaluation-appeals', vars.evaluationId] })
    },
  })
}

export function useAcceptAppealMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (appealId: number) => acceptEvaluationAppeal(appealId),
    onSuccess: async () => {
      await qc.invalidateQueries({ queryKey: ['supplier-evaluations'] })
      await qc.invalidateQueries({ queryKey: ['supplier-evaluation-appeals'] })
    },
  })
}

export function useRejectAppealMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ appealId, note }: { appealId: number; note?: string }) =>
      rejectEvaluationAppeal(appealId, note),
    onSuccess: async () => {
      await qc.invalidateQueries({ queryKey: ['supplier-evaluation-appeals'] })
    },
  })
}
