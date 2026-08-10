import { describe, expect, it } from 'vitest'

import { ar } from '@/locales/ar'

import type { MeetingFormState } from '../types/meetings'
import {
  availableLifecycleActions,
  canEditMeeting,
  canShowCreateMeetingCta,
  fromDatetimeLocalValue,
  isMeetingLocked,
  isMeetingToday,
  mapMeetingErrorCode,
  resolveMeetingsListState,
  toDatetimeLocalValue,
  validateAgendaItemForm,
  validateMeetingForm,
  validateRecommendationForm,
} from './meetingValidation'

function form(partial: Partial<MeetingFormState> = {}): MeetingFormState {
  return {
    title: '',
    description: '',
    scheduled_at: '',
    location_type: 'physical',
    location_text: '',
    meeting_link: '',
    organization_unit_id: '',
    chairperson_employee_id: '',
    secretary_employee_id: '',
    notes: '',
    ...partial,
  }
}

describe('meetings list view states', () => {
  it('resolves loading / empty / error / ready', () => {
    expect(resolveMeetingsListState({ isLoading: true, isError: false, count: 0 })).toBe(
      'loading',
    )
    expect(resolveMeetingsListState({ isLoading: false, isError: true, count: 0 })).toBe('error')
    expect(resolveMeetingsListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
    expect(resolveMeetingsListState({ isLoading: false, isError: false, count: 3 })).toBe('ready')
  })
})

describe('create / edit drawer validation', () => {
  it('requires title only', () => {
    expect(validateMeetingForm(form())).toEqual({ title: 'required' })
    expect(validateMeetingForm(form({ title: 'اجتماع' }))).toEqual({})
  })

  it('validates agenda and recommendation titles', () => {
    expect(validateAgendaItemForm({ title: '' })).toEqual({ title: 'required' })
    expect(validateRecommendationForm({ title: '  ' })).toEqual({ title: 'required' })
    expect(validateRecommendationForm({ title: 'توصية' })).toEqual({})
  })
})

describe('permission-aware CTAs and edit gates', () => {
  it('shows create CTA only with meetings.create', () => {
    expect(canShowCreateMeetingCta(['meetings.view'])).toBe(false)
    expect(canShowCreateMeetingCta(['meetings.view', 'meetings.create'])).toBe(true)
  })

  it('locks completed and cancelled meetings', () => {
    expect(isMeetingLocked('completed')).toBe(true)
    expect(isMeetingLocked('cancelled')).toBe(true)
    expect(isMeetingLocked('draft')).toBe(false)
    expect(canEditMeeting('scheduled', ['meetings.update'])).toBe(true)
    expect(canEditMeeting('completed', ['meetings.update'])).toBe(false)
  })
})

describe('lifecycle actions by status', () => {
  it('offers schedule and cancel for draft', () => {
    expect(availableLifecycleActions('draft').map((a) => a.action)).toEqual([
      'schedule',
      'cancel',
    ])
  })

  it('offers reschedule, start and cancel for scheduled', () => {
    expect(availableLifecycleActions('scheduled').map((a) => a.action)).toEqual([
      'reschedule',
      'start',
      'cancel',
    ])
  })

  it('offers complete and cancel for in_progress', () => {
    expect(availableLifecycleActions('in_progress').map((a) => a.action)).toEqual([
      'complete',
      'cancel',
    ])
  })

  it('requires datetime for schedule/reschedule and comment for cancel', () => {
    const draft = availableLifecycleActions('draft')
    expect(draft.find((a) => a.action === 'schedule')?.requiresDatetime).toBe(true)
    expect(draft.find((a) => a.action === 'cancel')?.requiresComment).toBe(true)
  })

  it('offers nothing for terminal statuses', () => {
    expect(availableLifecycleActions('completed')).toEqual([])
    expect(availableLifecycleActions('cancelled')).toEqual([])
  })
})

describe('error mapping and Arabic copy', () => {
  it('maps MEETING_MINUTES_REQUIRED clearly', () => {
    expect(mapMeetingErrorCode('MEETING_MINUTES_REQUIRED')).toBe('MEETING_MINUTES_REQUIRED')
    expect(ar.meetings.errors.MEETING_MINUTES_REQUIRED).toMatch(/محضر/)
  })

  it('maps completion requirements error', () => {
    expect(mapMeetingErrorCode('MEETING_COMPLETION_REQUIREMENTS_NOT_MET')).toBe(
      'MEETING_COMPLETION_REQUIREMENTS_NOT_MET',
    )
    expect(ar.meetings.errors.MEETING_COMPLETION_REQUIREMENTS_NOT_MET).toMatch(/محضر|اكتمال/)
  })

  it('falls back to generic for unknown codes', () => {
    expect(mapMeetingErrorCode('UNKNOWN')).toBe('generic')
  })
})

describe('datetime helpers and today chip', () => {
  it('round-trips datetime-local values', () => {
    const local = '2026-08-15T10:30'
    const iso = fromDatetimeLocalValue(local)
    expect(iso).toBeTruthy()
    expect(toDatetimeLocalValue(iso)).toBe(local)
  })

  it('detects today scheduled meetings', () => {
    const now = new Date()
    const pad = (n: number) => String(n).padStart(2, '0')
    const todayIso = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T12:00:00`
    expect(isMeetingToday(todayIso)).toBe(true)
    expect(isMeetingToday('2020-01-01T12:00:00')).toBe(false)
    expect(isMeetingToday(null)).toBe(false)
  })
})

describe('list column and status labels', () => {
  it('exposes required table column labels', () => {
    expect(ar.meetings.columns.meetingNumber).toBe('رقم الاجتماع')
    expect(ar.meetings.columns.title).toBe('العنوان')
    expect(ar.meetings.columns.scheduledAt).toBe('الموعد')
    expect(ar.meetings.columns.chairperson).toBe('رئيس الجلسة')
    expect(ar.meetings.columns.organizationUnit).toBe('الوحدة التنظيمية')
    expect(ar.meetings.columns.status).toBe('الحالة')
    expect(ar.meetings.columns.attendeeCount).toBe('الحضور')
    expect(ar.meetings.columns.actions).toBe('إجراءات')
  })

  it('exposes Arabic status labels from UI spec', () => {
    expect(ar.meetings.status.draft).toBe('مسودة')
    expect(ar.meetings.status.scheduled).toBe('مجدول')
    expect(ar.meetings.status.in_progress).toBe('جارية')
    expect(ar.meetings.status.completed).toBe('مكتملة')
    expect(ar.meetings.status.cancelled).toBe('ملغاة')
  })

  it('exposes attendance and recommendation statuses', () => {
    expect(ar.meetings.attendance.invited).toBe('مدعو')
    expect(ar.meetings.attendance.attended).toBe('حضر')
    expect(ar.meetings.attendance.absent).toBe('غائب')
    expect(ar.meetings.attendance.excused).toBe('معتذر')
    expect(ar.meetings.recommendationStatus.draft).toBe('مسودة')
    expect(ar.meetings.recommendationStatus.final).toBe('نهائية')
  })

  it('exposes page header and lifecycle CTAs from UI spec', () => {
    expect(ar.meetings.title).toBe('الاجتماعات')
    expect(ar.meetings.subtitle).toBe('إدارة الاجتماعات ومحاضرها وتوصياتها')
    expect(ar.meetings.add).toBe('إضافة اجتماع')
    expect(ar.meetings.empty).toBe('لا توجد اجتماعات بعد')
    expect(ar.meetings.lifecycle.schedule).toBe('جدولة الاجتماع')
    expect(ar.meetings.lifecycle.reschedule).toBe('إعادة الجدولة')
    expect(ar.meetings.lifecycle.start).toBe('بدء الاجتماع')
    expect(ar.meetings.lifecycle.complete).toBe('إنهاء الاجتماع')
    expect(ar.meetings.lifecycle.cancel).toBe('إلغاء الاجتماع')
    expect(ar.meetings.upcomingBadge).toBe('قادم')
    expect(ar.meetings.todayBadge).toBe('اليوم')
    expect(ar.meetings.meetingNumberPlaceholder).toBe('يُولَّد تلقائياً')
    expect(ar.documents.entitySection.title).toBe('المستندات')
    expect(ar.meetings.systemActor).toBe('النظام')
    expect(ar.nav.meetings).toBe('الاجتماعات')
  })

  it('exposes details section titles', () => {
    expect(ar.meetings.sections.overview).toBe('نظرة عامة')
    expect(ar.meetings.sections.attendees).toBe('الحضور')
    expect(ar.meetings.sections.agenda).toBe('جدول الأعمال')
    expect(ar.meetings.sections.minutes).toBe('المحضر')
    expect(ar.meetings.sections.recommendations).toBe('التوصيات')
  })
})
