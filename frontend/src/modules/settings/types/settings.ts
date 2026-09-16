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

export interface TenantSettingsTechnical {
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
    mail_from_address?: string | null
    mail_from_name?: string | null
  }
}
