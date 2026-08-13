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

export interface TenantSettings {
  general: TenantSettingsGeneral
  regional: TenantSettingsRegional
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
}
