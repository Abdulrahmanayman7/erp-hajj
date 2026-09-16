import { describe, expect, it } from 'vitest'

import {
  buildSettingsPatch,
  emptySettingsForm,
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
  technical: {
    status: 'unavailable',
    deliverable: false,
    mail_mailer: null,
    mail_host: null,
    mail_port: null,
    mail_encryption: null,
    mail_username: null,
    mail_password_configured: false,
    mail_from_address: null,
    mail_from_name: null,
  },
}

describe('settingsValidation', () => {
  it('maps API payload to form state', () => {
    const form = settingsToForm(sample)
    expect(form.name).toBe('رفيع')
    expect(form.contact_phone).toBe('')
    expect(form.timezone).toBe('Asia/Riyadh')
    expect(form.mail_from_address).toBe('')
    expect(form.mail_from_name).toBe('')
    expect(form.mail_password_configured).toBe(false)
    expect(form.mail_password).toBe('')
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

  it('builds technical mail sender patch', () => {
    const baseline = settingsToForm(sample)
    const form = {
      ...baseline,
      mail_from_address: 'noreply@example.com',
      mail_from_name: 'رفيع ERP',
    }
    expect(buildSettingsPatch(form, baseline)).toEqual({
      technical: {
        mail_from_address: 'noreply@example.com',
        mail_from_name: 'رفيع ERP',
      },
    })
  })

  it('builds SMTP patch and preserves blank password', () => {
    const baseline = settingsToForm({
      ...sample,
      technical: {
        ...sample.technical,
        status: 'tenant_smtp',
        deliverable: true,
        mail_mailer: 'smtp',
        mail_host: 'smtp.hostinger.com',
        mail_port: 465,
        mail_encryption: 'ssl',
        mail_username: 'noreply@example.com',
        mail_password_configured: true,
      },
    })
    expect(baseline.mail_password).toBe('')
    expect(isSettingsDirty({ ...baseline, mail_from_name: 'x' }, baseline)).toBe(true)
    const patch = buildSettingsPatch({ ...baseline, mail_from_name: 'x' }, baseline)
    expect(patch?.technical?.mail_password).toBeUndefined()
    expect(patch?.technical?.mail_from_name).toBe('x')
  })

  it('includes new SMTP password only when typed', () => {
    const baseline = settingsToForm(sample)
    const form = {
      ...baseline,
      mail_mailer: 'smtp',
      mail_host: 'smtp.example.com',
      mail_port: '465',
      mail_encryption: 'ssl',
      mail_username: 'u@example.com',
      mail_password: 'secret-pass',
    }
    const patch = buildSettingsPatch(form, baseline)
    expect(patch?.technical?.mail_mailer).toBe('smtp')
    expect(patch?.technical?.mail_password).toBe('secret-pass')
  })

  it('validates incomplete SMTP', () => {
    const errors = validateSettingsForm({
      ...emptySettingsForm(),
      name: 'رفيع',
      mail_mailer: 'smtp',
      mail_host: '',
      mail_port: '',
    })
    expect(errors.mail_host).toBeTruthy()
    expect(errors.mail_port).toBeTruthy()
  })

  it('returns to clean when a changed value is reverted', () => {
    const baseline = settingsToForm(sample)
    const changed = { ...baseline, name: 'اسم مؤقت' }

    expect(isSettingsDirty(changed, baseline)).toBe(true)
    expect(isSettingsDirty({ ...changed, name: baseline.name }, baseline)).toBe(false)
  })

  it('sends only supported mutable fields and never locale', () => {
    const baseline = settingsToForm(sample)
    const payload = buildSettingsPatch(
      { ...baseline, contact_email: '', timezone: 'Asia/Dubai' },
      baseline,
    )

    expect(payload).toEqual({
      general: { contact_email: null },
      regional: { timezone: 'Asia/Dubai' },
    })
    expect(JSON.stringify(payload)).not.toContain('locale')
  })

  it('validates required name and email', () => {
    const errors = validateSettingsForm({
      ...emptySettingsForm(),
      name: '',
      contact_email: 'bad',
    })
    expect(errors.name).toBeTruthy()
    expect(errors.contact_email).toBeTruthy()
  })

  it('rejects invalid mail_from_address', () => {
    const errors = validateSettingsForm({
      ...emptySettingsForm(),
      name: 'رفيع',
      mail_from_address: 'bad',
    })
    expect(errors.mail_from_address).toBeTruthy()
  })

  it('rejects HTML in name', () => {
    const errors = validateSettingsForm({
      ...emptySettingsForm(),
      name: '<b>x</b>',
    })
    expect(errors.name).toBeTruthy()
  })

  it('validates contact fields with the API length and HTML rules', () => {
    const errors = validateSettingsForm({
      ...emptySettingsForm(),
      name: 'رفيع',
      contact_name: '<strong>علي</strong>',
      contact_email: 'a'.repeat(250) + '@example.com',
      contact_phone: '0'.repeat(51),
    })

    expect(errors.contact_name).toBeTruthy()
    expect(errors.contact_email).toBeTruthy()
    expect(errors.contact_phone).toBeTruthy()
  })

  it('lists curated PHP-compatible IANA timezones including Asia/Riyadh', () => {
    const zones = listTimezones()
    expect(zones.includes('Asia/Riyadh')).toBe(true)
    expect(zones.includes('Asia/Jordan')).toBe(false)
  })

  it('ensures an existing tenant timezone remains selectable', () => {
    expect(listTimezones('Pacific/Honolulu')).toContain('Pacific/Honolulu')
  })

  it('rejects unsupported timezone values before PATCH', () => {
    const errors = validateSettingsForm({
      ...emptySettingsForm(),
      name: 'رفيع',
      timezone: 'Asia/Jordan',
    })
    expect(errors.timezone).toBeTruthy()
  })

  it('allows an existing non-curated timezone only when it is the loaded value', () => {
    const errors = validateSettingsForm(
      {
        ...emptySettingsForm(),
        name: 'رفيع',
        timezone: 'Pacific/Honolulu',
      },
      { allowTimezone: 'Pacific/Honolulu' },
    )
    expect(errors.timezone).toBeUndefined()
  })
})
