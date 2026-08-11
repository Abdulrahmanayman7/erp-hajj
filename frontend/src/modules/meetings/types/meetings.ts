export type MeetingStatus = 'draft' | 'scheduled' | 'in_progress' | 'completed' | 'cancelled'

export type MeetingLocationType = 'physical' | 'remote' | 'hybrid'

export type AttendanceStatus = 'invited' | 'attended' | 'absent' | 'excused'

export type RecommendationStatus = 'draft' | 'final'

export interface MeetingEmployeeSummary {
  id: number
  employee_number: string
  full_name: string
}

export interface MeetingOrgUnitSummary {
  id: number
  name: string
  code: string
}

export interface MeetingActorSummary {
  id: number
  name: string
}

export interface MeetingAttendee {
  id: number
  employee: MeetingEmployeeSummary
  attendance_status: AttendanceStatus
}

export interface MeetingAgendaItem {
  id: number
  title: string
  description: string | null
  sort_order: number
}

export interface MeetingRecommendation {
  id: number
  title: string
  description: string | null
  status: RecommendationStatus
  sort_order: number
  agenda_item_id: number | null
  owner: MeetingEmployeeSummary | null
  created_by: MeetingActorSummary | null
  linked_decision?: {
    id: number
    decision_number: string
    status: string
  } | null
}

export interface MeetingTransition {
  id: number
  from_status: MeetingStatus | null
  to_status: MeetingStatus
  comment: string | null
  actor: MeetingActorSummary | null
  correlation_id: string | null
  created_at: string | null
}

export interface Meeting {
  id: number
  meeting_number: string
  title: string
  description: string | null
  status: MeetingStatus
  scheduled_at: string | null
  started_at: string | null
  ended_at: string | null
  location_type: MeetingLocationType
  location_text: string | null
  meeting_link: string | null
  minutes_body?: string | null
  notes: string | null
  attendee_count?: number
  is_upcoming: boolean
  organization_unit: MeetingOrgUnitSummary | null
  chairperson: MeetingEmployeeSummary | null
  secretary: MeetingEmployeeSummary | null
  created_by: MeetingActorSummary | null
  attendees?: MeetingAttendee[]
  agenda_items?: MeetingAgendaItem[]
  recommendations?: MeetingRecommendation[]
  transitions?: MeetingTransition[]
  created_at: string | null
  updated_at: string | null
}

export interface MeetingsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListMeetingsParams {
  search?: string
  status?: MeetingStatus | 'all' | ''
  organization_unit_id?: number | ''
  chairperson_employee_id?: number | ''
  date_from?: string
  date_to?: string
  upcoming?: boolean | 1 | 0 | '' | string
  past?: boolean | 1 | 0 | '' | string
  page?: number
  per_page?: number
  sort?: string
  direction?: string
}

export interface CreateMeetingPayload {
  title: string
  description?: string | null
  location_type?: MeetingLocationType
  location_text?: string | null
  meeting_link?: string | null
  organization_unit_id?: number | null
  chairperson_employee_id?: number | null
  secretary_employee_id?: number | null
  notes?: string | null
  scheduled_at?: string | null
}

export interface UpdateMeetingPayload {
  title?: string
  description?: string | null
  location_type?: MeetingLocationType
  location_text?: string | null
  meeting_link?: string | null
  organization_unit_id?: number | null
  chairperson_employee_id?: number | null
  secretary_employee_id?: number | null
  notes?: string | null
  scheduled_at?: string | null
}

export interface MeetingSchedulePayload {
  scheduled_at: string
}

export interface MeetingReschedulePayload {
  scheduled_at: string
  comment?: string | null
}

export interface MeetingTransitionPayload {
  comment?: string | null
}

export interface MeetingCancelPayload {
  comment: string
}

export interface UpdateMinutesPayload {
  minutes_body: string
}

export interface CreateAttendeePayload {
  employee_id: number
}

export interface UpdateAttendeePayload {
  attendance_status: AttendanceStatus
}

export interface CreateAgendaItemPayload {
  title: string
  description?: string | null
  sort_order?: number | null
}

export interface UpdateAgendaItemPayload {
  title?: string
  description?: string | null
  sort_order?: number | null
}

export interface CreateRecommendationPayload {
  title: string
  description?: string | null
  agenda_item_id?: number | null
  owner_employee_id?: number | null
  status?: RecommendationStatus
  sort_order?: number | null
}

export interface UpdateRecommendationPayload {
  title?: string
  description?: string | null
  agenda_item_id?: number | null
  owner_employee_id?: number | null
  status?: RecommendationStatus
  sort_order?: number | null
}

export interface MeetingFormState {
  title: string
  description: string
  scheduled_at: string
  location_type: MeetingLocationType
  location_text: string
  meeting_link: string
  organization_unit_id: number | ''
  chairperson_employee_id: number | ''
  secretary_employee_id: number | ''
  notes: string
}

export type MeetingLifecycleAction = 'schedule' | 'reschedule' | 'start' | 'complete' | 'cancel'
