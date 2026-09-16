import type { TenantSettings, UpdateTenantSettingsPayload } from '../types/settings'

export interface SettingsFormState {
  name: string
  contact_name: string
  contact_email: string
  contact_phone: string
  timezone: string
  mail_mailer: string
  mail_host: string
  /** Always a string in form state (HTML inputs). Never call methods on raw API values. */
  mail_port: string
  /**
   * Persisted/base: empty string means API null (never invent 'ssl').
   * Editable UI may apply SMTP_ENCRYPTION_UI_DEFAULT when host is present.
   */
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

/** Editable UI default when configuring SMTP without a persisted encryption value. */
export const SMTP_ENCRYPTION_UI_DEFAULT = 'ssl'

/**
 * Safe API → form text conversion.
 * null/undefined → ''; number 587 → '587'; string '587' → '587'
 */
export function toFormText(value: unknown): string {
  if (value === null || value === undefined) {
    return ''
  }
  if (typeof value === 'string') {
    return value
  }
  if (typeof value === 'number' || typeof value === 'boolean' || typeof value === 'bigint') {
    return String(value)
  }
  return ''
}

/**
 * Normalize editable form fields so .trim() is always safe.
 * Does NOT invent persisted SMTP values (null encryption stays '').
 */
export function normalizeSettingsForm(form: SettingsFormState): SettingsFormState {
  return {
    name: toFormText(form.name),
    contact_name: toFormText(form.contact_name),
    contact_email: toFormText(form.contact_email),
    contact_phone: toFormText(form.contact_phone),
    timezone: toFormText(form.timezone) || 'Asia/Riyadh',
    mail_mailer: toFormText(form.mail_mailer),
    mail_host: toFormText(form.mail_host),
    mail_port: toFormText(form.mail_port).trim(),
    mail_encryption: toFormText(form.mail_encryption).trim().toLowerCase(),
    mail_username: toFormText(form.mail_username),
    mail_password: toFormText(form.mail_password),
    mail_password_clear: Boolean(form.mail_password_clear),
    mail_password_configured: Boolean(form.mail_password_configured),
    mail_from_address: toFormText(form.mail_from_address),
    mail_from_name: toFormText(form.mail_from_name),
    mail_status: toFormText(form.mail_status) || 'unavailable',
    mail_deliverable: Boolean(form.mail_deliverable),
  }
}

/**
 * Apply editable-only SMTP UI defaults without mutating persisted baseline truth.
 * When host is present but encryption is empty (API null), default the editor to SSL.
 */
export function withEditableSmtpDefaults(form: SettingsFormState): SettingsFormState {
  const normalized = normalizeSettingsForm(form)
  if (normalized.mail_host.trim() !== '' && normalized.mail_encryption === '') {
    return { ...normalized, mail_encryption: SMTP_ENCRYPTION_UI_DEFAULT }
  }
  return normalized
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
    mail_encryption: '',
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

/**
 * Hydrate persisted/base state from API — preserve backend truth.
 * null mail_encryption → '' (never silently 'ssl').
 */
export function settingsToForm(data: TenantSettings): SettingsFormState {
  return normalizeSettingsForm({
    name: toFormText(data.general?.name),
    contact_name: toFormText(data.general?.contact_name),
    contact_email: toFormText(data.general?.contact_email),
    contact_phone: toFormText(data.general?.contact_phone),
    timezone: toFormText(data.regional?.timezone) || 'Asia/Riyadh',
    mail_mailer: toFormText(data.technical?.mail_mailer),
    mail_host: toFormText(data.technical?.mail_host),
    mail_port: toFormText(data.technical?.mail_port),
    mail_encryption: toFormText(data.technical?.mail_encryption),
    mail_username: toFormText(data.technical?.mail_username),
    mail_password: '',
    mail_password_clear: false,
    mail_password_configured: Boolean(data.technical?.mail_password_configured),
    mail_from_address: toFormText(data.technical?.mail_from_address),
    mail_from_name: toFormText(data.technical?.mail_from_name),
    mail_status: toFormText(data.technical?.status) || 'unavailable',
    mail_deliverable: Boolean(data.technical?.deliverable),
  })
}

/**
 * PATCH `technical.mail_port`: blank → null; otherwise integer (backend rule: integer 1..65535).
 */
export function mailPortToApiValue(port: string): number | null {
  const trimmed = toFormText(port).trim()
  if (trimmed === '') {
    return null
  }
  const parsed = Number.parseInt(trimmed, 10)
  return Number.isInteger(parsed) ? parsed : null
}

function isSmtpConfiguring(form: SettingsFormState): boolean {
  return (
    form.mail_mailer.trim() === 'smtp' ||
    form.mail_host.trim() !== '' ||
    form.mail_username.trim() !== '' ||
    form.mail_password.trim() !== ''
  )
}

/**
 * Effective encryption for an editable SMTP configuration.
 * Empty persisted encryption while configuring → UI default so it can be PATCHed.
 */
export function effectiveSmtpEncryption(form: SettingsFormState): string {
  const normalized = normalizeSettingsForm(form)
  if (normalized.mail_encryption !== '') {
    return normalized.mail_encryption
  }
  if (isSmtpConfiguring(normalized) && normalized.mail_host.trim() !== '') {
    return SMTP_ENCRYPTION_UI_DEFAULT
  }
  return ''
}

export function isSettingsDirty(form: SettingsFormState, baseline: SettingsFormState): boolean {
  const current = normalizeSettingsForm(form)
  const base = normalizeSettingsForm(baseline)
  const currentEncryption = effectiveSmtpEncryption(current)
  const baseEncryption = base.mail_encryption

  return (
    current.name.trim() !== base.name.trim() ||
    current.contact_name.trim() !== base.contact_name.trim() ||
    current.contact_email.trim() !== base.contact_email.trim() ||
    current.contact_phone.trim() !== base.contact_phone.trim() ||
    current.timezone !== base.timezone ||
    current.mail_mailer.trim() !== base.mail_mailer.trim() ||
    current.mail_host.trim() !== base.mail_host.trim() ||
    current.mail_port.trim() !== base.mail_port.trim() ||
    currentEncryption !== baseEncryption ||
    current.mail_username.trim() !== base.mail_username.trim() ||
    current.mail_password.trim() !== '' ||
    current.mail_password_clear !== base.mail_password_clear ||
    current.mail_from_address.trim() !== base.mail_from_address.trim() ||
    current.mail_from_name.trim() !== base.mail_from_name.trim()
  )
}

export function buildSettingsPatch(
  form: SettingsFormState,
  baseline: SettingsFormState,
): UpdateTenantSettingsPayload | null {
  const current = normalizeSettingsForm(form)
  const base = normalizeSettingsForm(baseline)

  const general: NonNullable<UpdateTenantSettingsPayload['general']> = {}
  const regional: NonNullable<UpdateTenantSettingsPayload['regional']> = {}
  const technical: NonNullable<UpdateTenantSettingsPayload['technical']> = {}

  if (current.name.trim() !== base.name.trim()) {
    general.name = current.name.trim()
  }
  if (current.contact_name.trim() !== base.contact_name.trim()) {
    general.contact_name = current.contact_name.trim() === '' ? null : current.contact_name.trim()
  }
  if (current.contact_email.trim() !== base.contact_email.trim()) {
    general.contact_email = current.contact_email.trim() === '' ? null : current.contact_email.trim()
  }
  if (current.contact_phone.trim() !== base.contact_phone.trim()) {
    general.contact_phone = current.contact_phone.trim() === '' ? null : current.contact_phone.trim()
  }
  if (current.timezone !== base.timezone) {
    regional.timezone = current.timezone
  }

  const smtpConfiguring = isSmtpConfiguring(current)
  const currentEncryption = effectiveSmtpEncryption(current)
  const baseEncryption = base.mail_encryption

  if (smtpConfiguring && current.mail_mailer.trim() !== base.mail_mailer.trim()) {
    technical.mail_mailer =
      current.mail_mailer.trim() === '' ? 'smtp' : current.mail_mailer.trim()
  } else if (!smtpConfiguring && base.mail_mailer) {
    technical.mail_mailer = null
  } else if (current.mail_mailer.trim() === 'smtp' && base.mail_mailer !== 'smtp') {
    technical.mail_mailer = 'smtp'
  }

  const smtpFieldChanged =
    current.mail_host.trim() !== base.mail_host.trim() ||
    current.mail_port.trim() !== base.mail_port.trim() ||
    currentEncryption !== baseEncryption ||
    current.mail_username.trim() !== base.mail_username.trim() ||
    current.mail_password.trim() !== '' ||
    current.mail_password_clear

  if (smtpFieldChanged && current.mail_host.trim() !== '') {
    const mailer = current.mail_mailer.trim() === '' ? 'smtp' : current.mail_mailer.trim()
    if (mailer !== base.mail_mailer.trim()) {
      technical.mail_mailer = mailer
    }
  }

  if (current.mail_host.trim() !== base.mail_host.trim()) {
    technical.mail_host = current.mail_host.trim() === '' ? null : current.mail_host.trim()
  }
  if (current.mail_port.trim() !== base.mail_port.trim()) {
    technical.mail_port = mailPortToApiValue(current.mail_port)
  }

  // Persist encryption when it differs from baseline (null/'' → ssl must be sent).
  if (currentEncryption !== baseEncryption) {
    technical.mail_encryption = currentEncryption === '' ? null : currentEncryption
  }

  if (current.mail_username.trim() !== base.mail_username.trim()) {
    technical.mail_username =
      current.mail_username.trim() === '' ? null : current.mail_username.trim()
  }
  if (current.mail_password.trim() !== '') {
    technical.mail_password = current.mail_password
  }
  if (current.mail_password_clear && !current.mail_password.trim()) {
    technical.mail_password_clear = true
  }
  if (current.mail_from_address.trim() !== base.mail_from_address.trim()) {
    technical.mail_from_address =
      current.mail_from_address.trim() === '' ? null : current.mail_from_address.trim()
  }
  if (current.mail_from_name.trim() !== base.mail_from_name.trim()) {
    technical.mail_from_name =
      current.mail_from_name.trim() === '' ? null : current.mail_from_name.trim()
  }

  if (
    base.mail_mailer === 'smtp' &&
    current.mail_host.trim() === '' &&
    current.mail_mailer.trim() === ''
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
  const current = withEditableSmtpDefaults(form)
  const errors: Record<string, string> = {}

  if (!current.name.trim()) {
    errors.name = 'اسم المنشأة مطلوب'
  } else if (current.name.trim().length > 255) {
    errors.name = 'اسم المنشأة يجب ألا يتجاوز 255 حرفًا'
  } else if (current.name.includes('<') || current.name.includes('>')) {
    errors.name = 'لا يُسمح بوسوم HTML'
  }
  if (current.contact_name.trim().length > 255) {
    errors.contact_name = 'جهة الاتصال الرئيسية يجب ألا تتجاوز 255 حرفًا'
  } else if (current.contact_name.includes('<') || current.contact_name.includes('>')) {
    errors.contact_name = 'لا يُسمح بوسوم HTML'
  }
  if (current.contact_email.trim()) {
    if (current.contact_email.trim().length > 255) {
      errors.contact_email = 'البريد الإلكتروني يجب ألا يتجاوز 255 حرفًا'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(current.contact_email.trim())) {
      errors.contact_email = 'البريد الإلكتروني غير صالح'
    }
  }
  if (current.contact_phone.trim().length > 50) {
    errors.contact_phone = 'رقم الهاتف طويل جداً'
  }
  if (!current.timezone.trim()) {
    errors.timezone = 'المنطقة الزمنية مطلوبة'
  } else if (
    !isSupportedTimezone(current.timezone) &&
    current.timezone !== options?.allowTimezone
  ) {
    errors.timezone = 'المنطقة الزمنية غير صالحة. استخدم معرف IANA فقط.'
  }

  const smtpActive =
    current.mail_mailer === 'smtp' ||
    current.mail_host.trim() !== '' ||
    current.mail_username.trim() !== '' ||
    current.mail_password.trim() !== ''

  if (smtpActive) {
    if (!current.mail_host.trim()) {
      errors.mail_host = 'خادم SMTP مطلوب'
    } else if (current.mail_host.trim().length > 255) {
      errors.mail_host = 'خادم SMTP يجب ألا يتجاوز 255 حرفًا'
    }
    if (!current.mail_port.trim()) {
      errors.mail_port = 'المنفذ مطلوب'
    } else {
      const port = Number.parseInt(current.mail_port.trim(), 10)
      if (!Number.isInteger(port) || port < 1 || port > 65535) {
        errors.mail_port = 'المنفذ يجب أن يكون بين 1 و 65535'
      }
    }
    const encryption = effectiveSmtpEncryption(current)
    if (!['tls', 'ssl', 'none'].includes(encryption)) {
      errors.mail_encryption = 'قيمة التشفير غير مدعومة'
    }
    if (current.mail_username.trim().length > 255) {
      errors.mail_username = 'اسم المستخدم يجب ألا يتجاوز 255 حرفًا'
    }
    if (
      current.mail_username.trim() &&
      !current.mail_password.trim() &&
      !current.mail_password_configured &&
      !current.mail_password_clear
    ) {
      errors.mail_password = 'كلمة مرور SMTP مطلوبة عند تحديد اسم المستخدم'
    }
  }

  if (current.mail_from_address.trim()) {
    if (current.mail_from_address.trim().length > 255) {
      errors.mail_from_address = 'بريد المرسل يجب ألا يتجاوز 255 حرفًا'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(current.mail_from_address.trim())) {
      errors.mail_from_address = 'بريد المرسل غير صالح'
    }
  }
  if (current.mail_from_name.trim().length > 255) {
    errors.mail_from_name = 'اسم المرسل يجب ألا يتجاوز 255 حرفًا'
  } else if (current.mail_from_name.includes('<') || current.mail_from_name.includes('>')) {
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
