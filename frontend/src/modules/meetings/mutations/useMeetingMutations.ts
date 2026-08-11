import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  addMeetingAttendee,
  cancelMeeting,
  completeMeeting,
  createMeeting,
  createMeetingAgendaItem,
  createMeetingRecommendation,
  deleteMeeting,
  deleteMeetingAgendaItem,
  deleteMeetingRecommendation,
  removeMeetingAttendee,
  rescheduleMeeting,
  scheduleMeeting,
  startMeeting,
  updateMeeting,
  updateMeetingAgendaItem,
  updateMeetingAttendee,
  updateMeetingMinutes,
  updateMeetingRecommendation,
} from '../api/meetingsApi'
import { meetingDetailQueryKey } from '../queries/useMeetingQuery'
import { meetingsQueryKey } from '../queries/useMeetingsQuery'
import type {
  CreateAgendaItemPayload,
  CreateAttendeePayload,
  CreateMeetingPayload,
  CreateRecommendationPayload,
  MeetingCancelPayload,
  MeetingReschedulePayload,
  MeetingSchedulePayload,
  MeetingTransitionPayload,
  UpdateAgendaItemPayload,
  UpdateAttendeePayload,
  UpdateMeetingPayload,
  UpdateMinutesPayload,
  UpdateRecommendationPayload,
} from '../types/meetings'

async function invalidateMeetingQueries(
  queryClient: ReturnType<typeof useQueryClient>,
  id?: number,
): Promise<void> {
  await queryClient.invalidateQueries({ queryKey: meetingsQueryKey })
  if (id != null) {
    await queryClient.invalidateQueries({ queryKey: meetingDetailQueryKey(id) })
  }
}

export function useCreateMeetingMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateMeetingPayload) => createMeeting(payload),
    onSuccess: async () => {
      await invalidateMeetingQueries(queryClient)
    },
  })
}

export function useUpdateMeetingMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateMeetingPayload }) =>
      updateMeeting(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.id)
    },
  })
}

export function useDeleteMeetingMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteMeeting(id),
    onSuccess: async (_data, id) => {
      await invalidateMeetingQueries(queryClient, id)
    },
  })
}

export function useScheduleMeetingMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: MeetingSchedulePayload }) =>
      scheduleMeeting(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.id)
    },
  })
}

export function useRescheduleMeetingMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: MeetingReschedulePayload }) =>
      rescheduleMeeting(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.id)
    },
  })
}

export function useStartMeetingMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: MeetingTransitionPayload }) =>
      startMeeting(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.id)
    },
  })
}

export function useCompleteMeetingMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: MeetingTransitionPayload }) =>
      completeMeeting(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.id)
    },
  })
}

export function useCancelMeetingMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: MeetingCancelPayload }) =>
      cancelMeeting(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.id)
    },
  })
}

export function useUpdateMinutesMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateMinutesPayload }) =>
      updateMeetingMinutes(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.id)
    },
  })
}

export function useAddAttendeeMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      meetingId,
      payload,
    }: {
      meetingId: number
      payload: CreateAttendeePayload
    }) => addMeetingAttendee(meetingId, payload),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.meetingId)
    },
  })
}

export function useUpdateAttendeeMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      meetingId,
      attendeeId,
      payload,
    }: {
      meetingId: number
      attendeeId: number
      payload: UpdateAttendeePayload
    }) => updateMeetingAttendee(meetingId, attendeeId, payload),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.meetingId)
    },
  })
}

export function useRemoveAttendeeMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ meetingId, attendeeId }: { meetingId: number; attendeeId: number }) =>
      removeMeetingAttendee(meetingId, attendeeId),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.meetingId)
    },
  })
}

export function useCreateAgendaItemMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      meetingId,
      payload,
    }: {
      meetingId: number
      payload: CreateAgendaItemPayload
    }) => createMeetingAgendaItem(meetingId, payload),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.meetingId)
    },
  })
}

export function useUpdateAgendaItemMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      meetingId,
      itemId,
      payload,
    }: {
      meetingId: number
      itemId: number
      payload: UpdateAgendaItemPayload
    }) => updateMeetingAgendaItem(meetingId, itemId, payload),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.meetingId)
    },
  })
}

export function useDeleteAgendaItemMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ meetingId, itemId }: { meetingId: number; itemId: number }) =>
      deleteMeetingAgendaItem(meetingId, itemId),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.meetingId)
    },
  })
}

export function useCreateRecommendationMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      meetingId,
      payload,
    }: {
      meetingId: number
      payload: CreateRecommendationPayload
    }) => createMeetingRecommendation(meetingId, payload),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.meetingId)
    },
  })
}

export function useUpdateRecommendationMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      meetingId,
      recommendationId,
      payload,
    }: {
      meetingId: number
      recommendationId: number
      payload: UpdateRecommendationPayload
    }) => updateMeetingRecommendation(meetingId, recommendationId, payload),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.meetingId)
    },
  })
}

export function useDeleteRecommendationMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      meetingId,
      recommendationId,
    }: {
      meetingId: number
      recommendationId: number
    }) => deleteMeetingRecommendation(meetingId, recommendationId),
    onSuccess: async (_data, variables) => {
      await invalidateMeetingQueries(queryClient, variables.meetingId)
    },
  })
}
