export interface TenantSettingsGeneral {
  name: string
  contact_name: string | null
  contact_email: string | null
  contact_phone: string | null
}

export interface TenantSettingsRegional {
  timezone: string
  locale: string
  locale_editable: boolean
}

export type MailDeliveryStatus = 'tenant_smtp' | 'server_fallback' | 'unavailable'

export interface TenantSettingsTechnical {
  status: MailDeliveryStatus
  deliverable: boolean
  mail_mailer: string | null
  mail_host: string | null
  mail_port: number | null
  mail_encryption: string | null
  mail_username: string | null
  mail_password_configured: boolean
  mail_from_address: string | null
  mail_from_name: string | null
}

export interface TenantSettings {
  general: TenantSettingsGeneral
  regional: TenantSettingsRegional
  technical: TenantSettingsTechnical
}

export interface UpdateTenantSettingsPayload {
  general?: {
    name?: string
    contact_name?: string | null
    contact_email?: string | null
    contact_phone?: string | null
  }
  regional?: {
    timezone?: string
  }
  technical?: {
    mail_mailer?: string | null
    mail_host?: string | null
    mail_port?: number | null
    mail_encryption?: string | null
    mail_username?: string | null
    mail_password?: string | null
    mail_password_clear?: boolean
    mail_from_address?: string | null
    mail_from_name?: string | null
  }
}
