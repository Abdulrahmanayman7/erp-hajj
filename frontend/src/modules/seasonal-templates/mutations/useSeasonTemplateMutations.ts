import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  approveSeasonTemplate,
  archiveSeasonTemplate,
  createSeasonTemplate,
  updateSeasonTemplate,
} from '../api/seasonTemplatesApi'
import { seasonTemplateDetailQueryKey, seasonTemplatesQueryKey } from '../queries/useSeasonTemplatesQuery'
import type {
  CreateSeasonTemplatePayload,
  UpdateSeasonTemplatePayload,
} from '../types/seasonTemplates'

async function invalidate(client: ReturnType<typeof useQueryClient>, id?: number) {
  await client.invalidateQueries({ queryKey: seasonTemplatesQueryKey })
  if (id) await client.invalidateQueries({ queryKey: seasonTemplateDetailQueryKey(id) })
}

export function useCreateSeasonTemplateMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateSeasonTemplatePayload) => createSeasonTemplate(payload),
    onSuccess: () => invalidate(client),
  })
}

export function useUpdateSeasonTemplateMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateSeasonTemplatePayload }) =>
      updateSeasonTemplate(id, payload),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useApproveSeasonTemplateMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => approveSeasonTemplate(id),
    onSuccess: (_, id) => invalidate(client, id),
  })
}

export function useArchiveSeasonTemplateMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => archiveSeasonTemplate(id),
    onSuccess: (_, id) => invalidate(client, id),
  })
}
