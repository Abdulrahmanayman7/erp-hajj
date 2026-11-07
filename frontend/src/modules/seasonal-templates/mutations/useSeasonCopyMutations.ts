import { useMutation, useQueryClient } from '@tanstack/vue-query'

import { createSeasonCopyPreview, executeSeasonCopy } from '../api/seasonCopyApi'
import { seasonCopyRunsQueryKey } from '../queries/useSeasonCopyQuery'
import type { CreateSeasonCopyPreviewPayload, ExecuteSeasonCopyPayload } from '../types/seasonCopy'

export function useCreateSeasonCopyPreviewMutation() {
  return useMutation({
    mutationFn: (payload: CreateSeasonCopyPreviewPayload) => createSeasonCopyPreview(payload),
  })
}

export function useExecuteSeasonCopyMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (payload: ExecuteSeasonCopyPayload) => executeSeasonCopy(payload),
    // A failed execution is also recorded as a run server-side, so refresh history either way.
    onSettled: () => client.invalidateQueries({ queryKey: seasonCopyRunsQueryKey }),
  })
}
