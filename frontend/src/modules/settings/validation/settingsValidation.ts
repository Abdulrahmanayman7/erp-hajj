import type { TenantSettings, UpdateTenantSettingsPayload } from '../types/settings'

export interface SettingsFormState {
  name: string
  contact_name: string
  contact_email: string
  contact_phone: string
  timezone: string
}

export function settingsToForm(data: TenantSettings): SettingsFormState {
  return {
    name: data.general.name ?? '',
    contact_name: data.general.contact_name ?? '',
    contact_email: data.general.contact_email ?? '',
    contact_phone: data.general.contact_phone ?? '',
    timezone: data.regional.timezone ?? 'Asia/Riyadh',
  }
}

export function isSettingsDirty(form: SettingsFormState, baseline: SettingsFormState): boolean {
  return (
    form.name.trim() !== baseline.name.trim() ||
    form.contact_name.trim() !== baseline.contact_name.trim() ||
    form.contact_email.trim() !== baseline.contact_email.trim() ||
    form.contact_phone.trim() !== baseline.contact_phone.trim() ||
    form.timezone !== baseline.timezone
  )
}

export function buildSettingsPatch(
  form: SettingsFormState,
  baseline: SettingsFormState,
): UpdateTenantSettingsPayload | null {
  const general: NonNullable<UpdateTenantSettingsPayload['general']> = {}
  const regional: NonNullable<UpdateTenantSettingsPayload['regional']> = {}

  if (form.name.trim() !== baseline.name.trim()) {
    general.name = form.name.trim()
  }
  if (form.contact_name.trim() !== baseline.contact_name.trim()) {
    general.contact_name = form.contact_name.trim() === '' ? null : form.contact_name.trim()
  }
  if (form.contact_email.trim() !== baseline.contact_email.trim()) {
    general.contact_email = form.contact_email.trim() === '' ? null : form.contact_email.trim()
  }
  if (form.contact_phone.trim() !== baseline.contact_phone.trim()) {
    general.contact_phone = form.contact_phone.trim() === '' ? null : form.contact_phone.trim()
  }
  if (form.timezone !== baseline.timezone) {
    regional.timezone = form.timezone
  }

  const payload: UpdateTenantSettingsPayload = {}
  if (Object.keys(general).length > 0) {
    payload.general = general
  }
  if (Object.keys(regional).length > 0) {
    payload.regional = regional
  }

  return Object.keys(payload).length > 0 ? payload : null
}

export function validateSettingsForm(form: SettingsFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!form.name.trim()) {
    errors.name = 'اسم المنشأة مطلوب'
  } else if (form.name.includes('<') || form.name.includes('>')) {
    errors.name = 'لا يُسمح بوسوم HTML'
  }
  if (form.contact_email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.contact_email.trim())) {
    errors.contact_email = 'البريد الإلكتروني غير صالح'
  }
  if (form.contact_phone.trim().length > 50) {
    errors.contact_phone = 'رقم الهاتف طويل جداً'
  }
  if (!form.timezone.trim()) {
    errors.timezone = 'المنطقة الزمنية مطلوبة'
  }
  return errors
}

export function listTimezones(): string[] {
  try {
    const intl = Intl as unknown as { supportedValuesOf?: (key: string) => string[] }
    if (typeof intl.supportedValuesOf === 'function') {
      return intl.supportedValuesOf('timeZone')
    }
  } catch {
    // fall through
  }

  return [
    'Asia/Riyadh',
    'Asia/Dubai',
    'Asia/Kuwait',
    'Asia/Bahrain',
    'Asia/Qatar',
    'Asia/Muscat',
    'Asia/Jordan',
    'Asia/Beirut',
    'Africa/Cairo',
    'Africa/Khartoum',
    'Europe/Istanbul',
    'Europe/London',
    'Europe/Paris',
    'America/New_York',
    'UTC',
  ]
}
