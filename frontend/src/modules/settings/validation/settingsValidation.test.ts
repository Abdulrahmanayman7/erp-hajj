import { describe, expect, it } from 'vitest'

import {
  buildSettingsPatch,
  isSettingsDirty,
  listTimezones,
  settingsToForm,
  validateSettingsForm,
} from './settingsValidation'
import type { TenantSettings } from '../types/settings'

const sample: TenantSettings = {
  general: {
    name: 'رفيع',
    contact_name: 'علي',
    contact_email: 'ops@example.com',
    contact_phone: null,
  },
  regional: {
    timezone: 'Asia/Riyadh',
    locale: 'ar',
    locale_editable: false,
  },
}

describe('settingsValidation', () => {
  it('maps API payload to form state', () => {
    const form = settingsToForm(sample)
    expect(form.name).toBe('رفيع')
    expect(form.contact_phone).toBe('')
    expect(form.timezone).toBe('Asia/Riyadh')
  })

  it('detects dirty state and builds partial patch', () => {
    const baseline = settingsToForm(sample)
    const form = { ...baseline, timezone: 'Africa/Cairo', contact_phone: '+9665' }
    expect(isSettingsDirty(form, baseline)).toBe(true)
    expect(buildSettingsPatch(form, baseline)).toEqual({
      general: { contact_phone: '+9665' },
      regional: { timezone: 'Africa/Cairo' },
    })
    expect(buildSettingsPatch(baseline, baseline)).toBeNull()
  })

  it('validates required name and email', () => {
    const errors = validateSettingsForm({
      name: '',
      contact_name: '',
      contact_email: 'bad',
      contact_phone: '',
      timezone: 'Asia/Riyadh',
    })
    expect(errors.name).toBeTruthy()
    expect(errors.contact_email).toBeTruthy()
  })

  it('rejects HTML in name', () => {
    const errors = validateSettingsForm({
      name: '<b>x</b>',
      contact_name: '',
      contact_email: '',
      contact_phone: '',
      timezone: 'Asia/Riyadh',
    })
    expect(errors.name).toBeTruthy()
  })

  it('lists IANA timezones including Asia/Riyadh', () => {
    expect(listTimezones().includes('Asia/Riyadh')).toBe(true)
  })
})
