import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as meetingsApi from './meetingsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from '@/shared/api/http'

describe('meetingsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPut).mockReset()
    vi.mocked(apiPatch).mockReset()
    vi.mocked(apiDelete).mockReset()
  })

  it('lists meetings with filters as query string', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [
        {
          id: 1,
          meeting_number: 'MTG-000001',
          title: 'اجتماع إداري',
          description: null,
          status: 'scheduled',
          scheduled_at: '2026-08-15T10:00:00+03:00',
          started_at: null,
          ended_at: null,
          location_type: 'physical',
          location_text: 'القاعة',
          meeting_link: null,
          notes: null,
          attendee_count: 3,
          is_upcoming: true,
          organization_unit: { id: 1, name: 'الإدارة', code: 'ADM' },
          chairperson: { id: 2, employee_number: 'EMP-000001', full_name: 'أحمد' },
          secretary: null,
          created_by: null,
          created_at: null,
          updated_at: null,
        },
      ],
      meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
    })

    const result = await meetingsApi.listMeetings({
      search: 'إداري',
      status: 'scheduled',
      organization_unit_id: 1,
      upcoming: true,
      date_from: '2026-08-01',
      date_to: '2026-08-31',
    })

    expect(result.data).toHaveLength(1)
    expect(result.data[0]?.meeting_number).toBe('MTG-000001')
    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/meetings?search=%D8%A5%D8%AF%D8%A7%D8%B1%D9%8A&status=scheduled&organization_unit_id=1&upcoming=1&date_from=2026-08-01&date_to=2026-08-31',
    )
  })

  it('creates, updates and deletes meetings', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, title: 'New', meeting_number: 'MTG-000002', status: 'draft' },
    })
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, title: 'Updated' },
    })
    vi.mocked(apiDelete).mockResolvedValue({
      success: true,
      message: '',
      data: null,
    })

    await meetingsApi.createMeeting({
      title: 'New',
      location_type: 'hybrid',
    })
    await meetingsApi.updateMeeting(1, { title: 'Updated' })
    await meetingsApi.deleteMeeting(1)

    expect(apiPost).toHaveBeenCalledWith('/api/v1/meetings', {
      title: 'New',
      location_type: 'hybrid',
    })
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/meetings/1', { title: 'Updated' })
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/meetings/1')
  })

  it('calls lifecycle transition endpoints', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, status: 'scheduled' },
    })

    await meetingsApi.scheduleMeeting(1, { scheduled_at: '2026-08-20T09:00:00.000Z' })
    await meetingsApi.rescheduleMeeting(1, {
      scheduled_at: '2026-08-21T09:00:00.000Z',
      comment: 'تعارض',
    })
    await meetingsApi.startMeeting(1)
    await meetingsApi.completeMeeting(1, { comment: 'انتهى' })
    await meetingsApi.cancelMeeting(1, { comment: 'ملغى' })

    expect(apiPost).toHaveBeenCalledWith('/api/v1/meetings/1/schedule', {
      scheduled_at: '2026-08-20T09:00:00.000Z',
    })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/meetings/1/reschedule', {
      scheduled_at: '2026-08-21T09:00:00.000Z',
      comment: 'تعارض',
    })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/meetings/1/start', {})
    expect(apiPost).toHaveBeenCalledWith('/api/v1/meetings/1/complete', {
      comment: 'انتهى',
    })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/meetings/1/cancel', {
      comment: 'ملغى',
    })
  })

  it('updates minutes via PUT', async () => {
    vi.mocked(apiPut).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, minutes_body: 'محضر' },
    })

    await meetingsApi.updateMeetingMinutes(1, { minutes_body: 'محضر' })

    expect(apiPut).toHaveBeenCalledWith('/api/v1/meetings/1/minutes', {
      minutes_body: 'محضر',
    })
  })

  it('manages attendees', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [{ id: 10, employee: { id: 2, employee_number: 'E1', full_name: 'سعد' }, attendance_status: 'invited' }],
    })
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 10, attendance_status: 'invited' },
    })
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 10, attendance_status: 'attended' },
    })
    vi.mocked(apiDelete).mockResolvedValue({
      success: true,
      message: '',
      data: null,
    })

    await meetingsApi.listMeetingAttendees(1)
    await meetingsApi.addMeetingAttendee(1, { employee_id: 2 })
    await meetingsApi.updateMeetingAttendee(1, 10, { attendance_status: 'attended' })
    await meetingsApi.removeMeetingAttendee(1, 10)

    expect(apiGet).toHaveBeenCalledWith('/api/v1/meetings/1/attendees')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/meetings/1/attendees', { employee_id: 2 })
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/meetings/1/attendees/10', {
      attendance_status: 'attended',
    })
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/meetings/1/attendees/10')
  })

  it('manages agenda items', async () => {
    vi.mocked(apiGet).mockResolvedValue({ success: true, message: '', data: [] })
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 5, title: 'بند', description: null, sort_order: 1 },
    })
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 5, title: 'بند محدّث', description: null, sort_order: 1 },
    })
    vi.mocked(apiDelete).mockResolvedValue({ success: true, message: '', data: null })

    await meetingsApi.listMeetingAgendaItems(1)
    await meetingsApi.createMeetingAgendaItem(1, { title: 'بند' })
    await meetingsApi.updateMeetingAgendaItem(1, 5, { title: 'بند محدّث' })
    await meetingsApi.deleteMeetingAgendaItem(1, 5)

    expect(apiGet).toHaveBeenCalledWith('/api/v1/meetings/1/agenda-items')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/meetings/1/agenda-items', { title: 'بند' })
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/meetings/1/agenda-items/5', {
      title: 'بند محدّث',
    })
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/meetings/1/agenda-items/5')
  })

  it('manages recommendations', async () => {
    vi.mocked(apiGet).mockResolvedValue({ success: true, message: '', data: [] })
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 7, title: 'توصية', status: 'draft' },
    })
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 7, title: 'توصية', status: 'final' },
    })
    vi.mocked(apiDelete).mockResolvedValue({ success: true, message: '', data: null })

    await meetingsApi.listMeetingRecommendations(1)
    await meetingsApi.createMeetingRecommendation(1, { title: 'توصية' })
    await meetingsApi.updateMeetingRecommendation(1, 7, { status: 'final' })
    await meetingsApi.deleteMeetingRecommendation(1, 7)

    expect(apiGet).toHaveBeenCalledWith('/api/v1/meetings/1/recommendations')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/meetings/1/recommendations', {
      title: 'توصية',
    })
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/meetings/1/recommendations/7', {
      status: 'final',
    })
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/meetings/1/recommendations/7')
  })

  it('fetches meeting details', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 3, meeting_number: 'MTG-000003', title: 'تفاصيل' },
    })

    const meeting = await meetingsApi.getMeeting(3)

    expect(meeting.id).toBe(3)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/meetings/3')
  })
})
