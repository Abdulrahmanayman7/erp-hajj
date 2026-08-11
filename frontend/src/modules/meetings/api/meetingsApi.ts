import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from '@/shared/api/http'

import type {
  CreateAgendaItemPayload,
  CreateAttendeePayload,
  CreateMeetingPayload,
  CreateRecommendationPayload,
  ListMeetingsParams,
  Meeting,
  MeetingAgendaItem,
  MeetingAttendee,
  MeetingCancelPayload,
  MeetingRecommendation,
  MeetingReschedulePayload,
  MeetingSchedulePayload,
  MeetingTransitionPayload,
  MeetingsListMeta,
  UpdateAgendaItemPayload,
  UpdateAttendeePayload,
  UpdateMeetingPayload,
  UpdateMinutesPayload,
  UpdateRecommendationPayload,
} from '../types/meetings'

function toQuery(params: ListMeetingsParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return
    }
    if (typeof value === 'boolean') {
      query.set(key, value ? '1' : '0')
      return
    }
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listMeetings(params: ListMeetingsParams = {}): Promise<{
  data: Meeting[]
  meta: MeetingsListMeta
}> {
  const response = await apiGet<Meeting[]>(`/api/v1/meetings${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as MeetingsListMeta,
  }
}

export async function getMeeting(id: number): Promise<Meeting> {
  const response = await apiGet<Meeting>(`/api/v1/meetings/${id}`)
  return response.data
}

export async function createMeeting(payload: CreateMeetingPayload): Promise<Meeting> {
  const response = await apiPost<Meeting>('/api/v1/meetings', payload)
  return response.data
}

export async function updateMeeting(
  id: number,
  payload: UpdateMeetingPayload,
): Promise<Meeting> {
  const response = await apiPatch<Meeting>(`/api/v1/meetings/${id}`, payload)
  return response.data
}

export async function deleteMeeting(id: number): Promise<void> {
  await apiDelete(`/api/v1/meetings/${id}`)
}

export async function scheduleMeeting(
  id: number,
  payload: MeetingSchedulePayload,
): Promise<Meeting> {
  const response = await apiPost<Meeting>(`/api/v1/meetings/${id}/schedule`, payload)
  return response.data
}

export async function rescheduleMeeting(
  id: number,
  payload: MeetingReschedulePayload,
): Promise<Meeting> {
  const response = await apiPost<Meeting>(`/api/v1/meetings/${id}/reschedule`, payload)
  return response.data
}

export async function startMeeting(
  id: number,
  payload: MeetingTransitionPayload = {},
): Promise<Meeting> {
  const response = await apiPost<Meeting>(`/api/v1/meetings/${id}/start`, payload)
  return response.data
}

export async function completeMeeting(
  id: number,
  payload: MeetingTransitionPayload = {},
): Promise<Meeting> {
  const response = await apiPost<Meeting>(`/api/v1/meetings/${id}/complete`, payload)
  return response.data
}

export async function cancelMeeting(
  id: number,
  payload: MeetingCancelPayload,
): Promise<Meeting> {
  const response = await apiPost<Meeting>(`/api/v1/meetings/${id}/cancel`, payload)
  return response.data
}

export async function updateMeetingMinutes(
  id: number,
  payload: UpdateMinutesPayload,
): Promise<Meeting> {
  const response = await apiPut<Meeting>(`/api/v1/meetings/${id}/minutes`, payload)
  return response.data
}

export async function listMeetingAttendees(meetingId: number): Promise<MeetingAttendee[]> {
  const response = await apiGet<MeetingAttendee[]>(`/api/v1/meetings/${meetingId}/attendees`)
  return response.data
}

export async function addMeetingAttendee(
  meetingId: number,
  payload: CreateAttendeePayload,
): Promise<MeetingAttendee> {
  const response = await apiPost<MeetingAttendee>(
    `/api/v1/meetings/${meetingId}/attendees`,
    payload,
  )
  return response.data
}

export async function updateMeetingAttendee(
  meetingId: number,
  attendeeId: number,
  payload: UpdateAttendeePayload,
): Promise<MeetingAttendee> {
  const response = await apiPatch<MeetingAttendee>(
    `/api/v1/meetings/${meetingId}/attendees/${attendeeId}`,
    payload,
  )
  return response.data
}

export async function removeMeetingAttendee(
  meetingId: number,
  attendeeId: number,
): Promise<void> {
  await apiDelete(`/api/v1/meetings/${meetingId}/attendees/${attendeeId}`)
}

export async function listMeetingAgendaItems(meetingId: number): Promise<MeetingAgendaItem[]> {
  const response = await apiGet<MeetingAgendaItem[]>(
    `/api/v1/meetings/${meetingId}/agenda-items`,
  )
  return response.data
}

export async function createMeetingAgendaItem(
  meetingId: number,
  payload: CreateAgendaItemPayload,
): Promise<MeetingAgendaItem> {
  const response = await apiPost<MeetingAgendaItem>(
    `/api/v1/meetings/${meetingId}/agenda-items`,
    payload,
  )
  return response.data
}

export async function updateMeetingAgendaItem(
  meetingId: number,
  itemId: number,
  payload: UpdateAgendaItemPayload,
): Promise<MeetingAgendaItem> {
  const response = await apiPatch<MeetingAgendaItem>(
    `/api/v1/meetings/${meetingId}/agenda-items/${itemId}`,
    payload,
  )
  return response.data
}

export async function deleteMeetingAgendaItem(
  meetingId: number,
  itemId: number,
): Promise<void> {
  await apiDelete(`/api/v1/meetings/${meetingId}/agenda-items/${itemId}`)
}

export async function listMeetingRecommendations(
  meetingId: number,
): Promise<MeetingRecommendation[]> {
  const response = await apiGet<MeetingRecommendation[]>(
    `/api/v1/meetings/${meetingId}/recommendations`,
  )
  return response.data
}

export async function createMeetingRecommendation(
  meetingId: number,
  payload: CreateRecommendationPayload,
): Promise<MeetingRecommendation> {
  const response = await apiPost<MeetingRecommendation>(
    `/api/v1/meetings/${meetingId}/recommendations`,
    payload,
  )
  return response.data
}

export async function updateMeetingRecommendation(
  meetingId: number,
  recommendationId: number,
  payload: UpdateRecommendationPayload,
): Promise<MeetingRecommendation> {
  const response = await apiPatch<MeetingRecommendation>(
    `/api/v1/meetings/${meetingId}/recommendations/${recommendationId}`,
    payload,
  )
  return response.data
}

export async function deleteMeetingRecommendation(
  meetingId: number,
  recommendationId: number,
): Promise<void> {
  await apiDelete(`/api/v1/meetings/${meetingId}/recommendations/${recommendationId}`)
}
