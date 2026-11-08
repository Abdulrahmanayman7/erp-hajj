import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  activateSeasonReminderRule,
  archiveSeasonReminderRule,
  createSeasonReminderRule,
  pauseSeasonReminderRule,
  updateSeasonReminderRule,
} from '../api/seasonReminderRulesApi'
import { seasonReminderRulesQueryKey } from '../queries/useSeasonReminderRulesQuery'
import type {
  CreateSeasonReminderRulePayload,
  UpdateSeasonReminderRulePayload,
} from '../types/seasonReminderRules'

function invalidate(client: ReturnType<typeof useQueryClient>) {
  return client.invalidateQueries({ queryKey: seasonReminderRulesQueryKey })
}

export function useCreateSeasonReminderRuleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ seasonId, payload }: { seasonId: number; payload: CreateSeasonReminderRulePayload }) =>
      createSeasonReminderRule(seasonId, payload),
    onSuccess: () => invalidate(client),
  })
}

export function useUpdateSeasonReminderRuleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateSeasonReminderRulePayload }) =>
      updateSeasonReminderRule(id, payload),
    onSuccess: () => invalidate(client),
  })
}

export function useActivateSeasonReminderRuleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => activateSeasonReminderRule(id),
    onSuccess: () => invalidate(client),
  })
}

export function usePauseSeasonReminderRuleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => pauseSeasonReminderRule(id),
    onSuccess: () => invalidate(client),
  })
}

export function useArchiveSeasonReminderRuleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => archiveSeasonReminderRule(id),
    onSuccess: () => invalidate(client),
  })
}
