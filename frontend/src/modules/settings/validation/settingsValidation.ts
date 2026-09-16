import type { TenantSettings, UpdateTenantSettingsPayload } from '../types/settings'

export interface SettingsFormState {
  name: string
  contact_name: string
  contact_email: string
  contact_phone: string
  timezone: string
  mail_mailer: string
  mail_host: string
  mail_port: string
  mail_encryption: string
  mail_username: string
  mail_password: string
  mail_password_clear: boolean
  mail_password_configured: boolean
  mail_from_address: string
  mail_from_name: string
  mail_status: string
  mail_deliverable: boolean
}

export function emptySettingsForm(): SettingsFormState {
  return {
    name: '',
    contact_name: '',
    contact_email: '',
    contact_phone: '',
    timezone: 'Asia/Riyadh',
    mail_mailer: '',
    mail_host: '',
    mail_port: '',
    mail_encryption: 'ssl',
    mail_username: '',
    mail_password: '',
    mail_password_clear: false,
    mail_password_configured: false,
    mail_from_address: '',
    mail_from_name: '',
    mail_status: 'unavailable',
    mail_deliverable: false,
  }
}

export function settingsToForm(data: TenantSettings): SettingsFormState {
  return {
    name: data.general.name ?? '',
    contact_name: data.general.contact_name ?? '',
    contact_email: data.general.contact_email ?? '',
    contact_phone: data.general.contact_phone ?? '',
    timezone: data.regional.timezone ?? 'Asia/Riyadh',
    mail_mailer: data.technical?.mail_mailer ?? '',
    mail_host: data.technical?.mail_host ?? '',
    mail_port:
      data.technical?.mail_port !== null && data.technical?.mail_port !== undefined
        ? String(data.technical.mail_port)
        : '',
    mail_encryption: data.technical?.mail_encryption ?? 'ssl',
    mail_username: data.technical?.mail_username ?? '',
    mail_password: '',
    mail_password_clear: false,
    mail_password_configured: Boolean(data.technical?.mail_password_configured),
    mail_from_address: data.technical?.mail_from_address ?? '',
    mail_from_name: data.technical?.mail_from_name ?? '',
    mail_status: data.technical?.status ?? 'unavailable',
    mail_deliverable: Boolean(data.technical?.deliverable),
  }
}

export function isSettingsDirty(form: SettingsFormState, baseline: SettingsFormState): boolean {
  return (
    form.name.trim() !== baseline.name.trim() ||
    form.contact_name.trim() !== baseline.contact_name.trim() ||
    form.contact_email.trim() !== baseline.contact_email.trim() ||
    form.contact_phone.trim() !== baseline.contact_phone.trim() ||
    form.timezone !== baseline.timezone ||
    form.mail_mailer.trim() !== baseline.mail_mailer.trim() ||
    form.mail_host.trim() !== baseline.mail_host.trim() ||
    form.mail_port.trim() !== baseline.mail_port.trim() ||
    form.mail_encryption.trim() !== baseline.mail_encryption.trim() ||
    form.mail_username.trim() !== baseline.mail_username.trim() ||
    form.mail_password.trim() !== '' ||
    form.mail_password_clear !== baseline.mail_password_clear ||
    form.mail_from_address.trim() !== baseline.mail_from_address.trim() ||
    form.mail_from_name.trim() !== baseline.mail_from_name.trim()
  )
}

export function buildSettingsPatch(
  form: SettingsFormState,
  baseline: SettingsFormState,
): UpdateTenantSettingsPayload | null {
  const general: NonNullable<UpdateTenantSettingsPayload['general']> = {}
  const regional: NonNullable<UpdateTenantSettingsPayload['regional']> = {}
  const technical: NonNullable<UpdateTenantSettingsPayload['technical']> = {}

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

  const smtpEnabled = form.mail_mailer.trim() === 'smtp' || form.mail_host.trim() !== ''

  if (smtpEnabled && form.mail_mailer.trim() !== baseline.mail_mailer.trim()) {
    technical.mail_mailer = form.mail_mailer.trim() === '' ? null : form.mail_mailer.trim()
  } else if (!smtpEnabled && baseline.mail_mailer) {
    technical.mail_mailer = null
  } else if (form.mail_mailer.trim() === 'smtp' && baseline.mail_mailer !== 'smtp') {
    technical.mail_mailer = 'smtp'
  }

  // When any SMTP field changes, ensure mailer is smtp if host present.
  const smtpFieldChanged =
    form.mail_host.trim() !== baseline.mail_host.trim() ||
    form.mail_port.trim() !== baseline.mail_port.trim() ||
    form.mail_encryption.trim() !== baseline.mail_encryption.trim() ||
    form.mail_username.trim() !== baseline.mail_username.trim() ||
    form.mail_password.trim() !== '' ||
    form.mail_password_clear

  if (smtpFieldChanged && form.mail_host.trim() !== '') {
    technical.mail_mailer = 'smtp'
  }

  if (form.mail_host.trim() !== baseline.mail_host.trim()) {
    technical.mail_host = form.mail_host.trim() === '' ? null : form.mail_host.trim()
  }
  if (form.mail_port.trim() !== baseline.mail_port.trim()) {
    if (form.mail_port.trim() === '') {
      technical.mail_port = null
    } else {
      technical.mail_port = Number.parseInt(form.mail_port.trim(), 10)
    }
  }
  if (form.mail_encryption.trim() !== baseline.mail_encryption.trim()) {
    technical.mail_encryption =
      form.mail_encryption.trim() === '' ? null : form.mail_encryption.trim()
  }
  if (form.mail_username.trim() !== baseline.mail_username.trim()) {
    technical.mail_username =
      form.mail_username.trim() === '' ? null : form.mail_username.trim()
  }
  if (form.mail_password.trim() !== '') {
    technical.mail_password = form.mail_password
  }
  if (form.mail_password_clear && !form.mail_password.trim()) {
    technical.mail_password_clear = true
  }
  if (form.mail_from_address.trim() !== baseline.mail_from_address.trim()) {
    technical.mail_from_address =
      form.mail_from_address.trim() === '' ? null : form.mail_from_address.trim()
  }
  if (form.mail_from_name.trim() !== baseline.mail_from_name.trim()) {
    technical.mail_from_name =
      form.mail_from_name.trim() === '' ? null : form.mail_from_name.trim()
  }

  // Clearing all SMTP: if user emptied host and mailer was set
  if (
    baseline.mail_mailer === 'smtp' &&
    form.mail_host.trim() === '' &&
    form.mail_mailer.trim() === ''
  ) {
    technical.mail_mailer = null
    technical.mail_host = null
    technical.mail_port = null
    technical.mail_encryption = null
    technical.mail_username = null
  }

  const payload: UpdateTenantSettingsPayload = {}
  if (Object.keys(general).length > 0) {
    payload.general = general
  }
  if (Object.keys(regional).length > 0) {
    payload.regional = regional
  }
  if (Object.keys(technical).length > 0) {
    payload.technical = technical
  }

  return Object.keys(payload).length > 0 ? payload : null
}

export function validateSettingsForm(
  form: SettingsFormState,
  options?: { allowTimezone?: string },
): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!form.name.trim()) {
    errors.name = 'اسم المنشأة مطلوب'
  } else if (form.name.trim().length > 255) {
    errors.name = 'اسم المنشأة يجب ألا يتجاوز 255 حرفًا'
  } else if (form.name.includes('<') || form.name.includes('>')) {
    errors.name = 'لا يُسمح بوسوم HTML'
  }
  if (form.contact_name.trim().length > 255) {
    errors.contact_name = 'جهة الاتصال الرئيسية يجب ألا تتجاوز 255 حرفًا'
  } else if (form.contact_name.includes('<') || form.contact_name.includes('>')) {
    errors.contact_name = 'لا يُسمح بوسوم HTML'
  }
  if (form.contact_email.trim()) {
    if (form.contact_email.trim().length > 255) {
      errors.contact_email = 'البريد الإلكتروني يجب ألا يتجاوز 255 حرفًا'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.contact_email.trim())) {
      errors.contact_email = 'البريد الإلكتروني غير صالح'
    }
  }
  if (form.contact_phone.trim().length > 50) {
    errors.contact_phone = 'رقم الهاتف طويل جداً'
  }
  if (!form.timezone.trim()) {
    errors.timezone = 'المنطقة الزمنية مطلوبة'
  } else if (
    !isSupportedTimezone(form.timezone) &&
    form.timezone !== options?.allowTimezone
  ) {
    errors.timezone = 'المنطقة الزمنية غير صالحة. استخدم معرف IANA فقط.'
  }

  const smtpActive =
    form.mail_mailer === 'smtp' ||
    form.mail_host.trim() !== '' ||
    form.mail_username.trim() !== '' ||
    form.mail_password.trim() !== ''

  if (smtpActive) {
    if (!form.mail_host.trim()) {
      errors.mail_host = 'خادم SMTP مطلوب'
    } else if (form.mail_host.trim().length > 255) {
      errors.mail_host = 'خادم SMTP يجب ألا يتجاوز 255 حرفًا'
    }
    if (!form.mail_port.trim()) {
      errors.mail_port = 'المنفذ مطلوب'
    } else {
      const port = Number.parseInt(form.mail_port.trim(), 10)
      if (!Number.isInteger(port) || port < 1 || port > 65535) {
        errors.mail_port = 'المنفذ يجب أن يكون بين 1 و 65535'
      }
    }
    if (!['tls', 'ssl', 'none'].includes(form.mail_encryption)) {
      errors.mail_encryption = 'قيمة التشفير غير مدعومة'
    }
    if (form.mail_username.trim().length > 255) {
      errors.mail_username = 'اسم المستخدم يجب ألا يتجاوز 255 حرفًا'
    }
    if (
      form.mail_username.trim() &&
      !form.mail_password.trim() &&
      !form.mail_password_configured &&
      !form.mail_password_clear
    ) {
      errors.mail_password = 'كلمة مرور SMTP مطلوبة عند تحديد اسم المستخدم'
    }
  }

  if (form.mail_from_address.trim()) {
    if (form.mail_from_address.trim().length > 255) {
      errors.mail_from_address = 'بريد المرسل يجب ألا يتجاوز 255 حرفًا'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.mail_from_address.trim())) {
      errors.mail_from_address = 'بريد المرسل غير صالح'
    }
  }
  if (form.mail_from_name.trim().length > 255) {
    errors.mail_from_name = 'اسم المرسل يجب ألا يتجاوز 255 حرفًا'
  } else if (form.mail_from_name.includes('<') || form.mail_from_name.includes('>')) {
    errors.mail_from_name = 'لا يُسمح بوسوم HTML'
  }
  return errors
}

/**
 * Curated IANA catalog verified against PHP `timezone_identifiers_list()`.
 * Do not use `Intl.supportedValuesOf('timeZone')` here — browser lists can
 * include identifiers the backend rejects (`SETTINGS_INVALID_TIMEZONE`).
 */
export const CURATED_IANA_TIMEZONES: readonly string[] = [
  'Africa/Algiers',
  'Africa/Cairo',
  'Africa/Casablanca',
  'Africa/Khartoum',
  'Africa/Tripoli',
  'Africa/Tunis',
  'America/Chicago',
  'America/Los_Angeles',
  'America/New_York',
  'America/Toronto',
  'Asia/Aden',
  'Asia/Amman',
  'Asia/Baghdad',
  'Asia/Bahrain',
  'Asia/Beirut',
  'Asia/Damascus',
  'Asia/Dubai',
  'Asia/Gaza',
  'Asia/Hebron',
  'Asia/Jerusalem',
  'Asia/Kuwait',
  'Asia/Muscat',
  'Asia/Qatar',
  'Asia/Riyadh',
  'Europe/Berlin',
  'Europe/Istanbul',
  'Europe/London',
  'Europe/Madrid',
  'Europe/Paris',
  'Europe/Rome',
  'UTC',
]

export function isSupportedTimezone(timezone: string): boolean {
  return CURATED_IANA_TIMEZONES.includes(timezone)
}

export function listTimezones(ensureTimezone?: string): string[] {
  const zones = [...CURATED_IANA_TIMEZONES]
  if (ensureTimezone && !zones.includes(ensureTimezone)) {
    zones.push(ensureTimezone)
    zones.sort((a, b) => a.localeCompare(b))
  }
  return zones
}
