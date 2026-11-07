import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  addComplaintNote,
  assignComplaint,
  closeComplaint,
  createComplaint,
  deleteComplaint,
  escalateComplaint,
  reopenComplaint,
  resolveComplaint,
  updateComplaint,
} from '../api/complaintsApi'
import { complaintDetailQueryKey, complaintsQueryKey } from '../queries/useComplaintsQuery'
import type {
  ComplaintAssignPayload,
  ComplaintClosePayload,
  ComplaintCreatePayload,
  ComplaintEscalatePayload,
  ComplaintNotePayload,
  ComplaintReopenPayload,
  ComplaintResolvePayload,
  ComplaintUpdatePayload,
} from '../types/complaints'

async function invalidate(client: ReturnType<typeof useQueryClient>, id?: number) {
  await client.invalidateQueries({ queryKey: complaintsQueryKey })
  if (id) await client.invalidateQueries({ queryKey: complaintDetailQueryKey(id) })
}

export function useCreateComplaintMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      payload,
      seasonId,
    }: {
      payload: ComplaintCreatePayload
      seasonId?: number | null
    }) => createComplaint(payload, seasonId),
    onSuccess: () => invalidate(client),
  })
}

export function useUpdateComplaintMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: ComplaintUpdatePayload
      seasonId?: number | null
    }) => updateComplaint(id, payload, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useAssignComplaintMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: ComplaintAssignPayload
      seasonId?: number | null
    }) => assignComplaint(id, payload, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useEscalateComplaintMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: ComplaintEscalatePayload
      seasonId?: number | null
    }) => escalateComplaint(id, payload, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useResolveComplaintMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: ComplaintResolvePayload
      seasonId?: number | null
    }) => resolveComplaint(id, payload, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useCloseComplaintMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: ComplaintClosePayload
      seasonId?: number | null
    }) => closeComplaint(id, payload, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useReopenComplaintMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: ComplaintReopenPayload
      seasonId?: number | null
    }) => reopenComplaint(id, payload, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useAddComplaintNoteMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: ComplaintNotePayload
      seasonId?: number | null
    }) => addComplaintNote(id, payload, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useDeleteComplaintMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      deleteComplaint(id, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}
