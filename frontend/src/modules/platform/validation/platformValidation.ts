export interface PlatformSetupFieldErrors {
  name?: string
  email?: string
  password?: string
  password_confirmation?: string
}

export function validatePlatformSetup(input: {
  name: string
  email: string
  password: string
  password_confirmation: string
}): PlatformSetupFieldErrors {
  const errors: PlatformSetupFieldErrors = {}

  if (!input.name.trim()) {
    errors.name = 'required'
  }

  if (!input.email.trim()) {
    errors.email = 'required'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.email.trim())) {
    errors.email = 'email'
  }

  if (!input.password) {
    errors.password = 'required'
  } else if (input.password.length < 8) {
    errors.password = 'min'
  } else if (!/[A-Za-z]/.test(input.password) || !/\d/.test(input.password)) {
    errors.password = 'policy'
  }

  if (!input.password_confirmation) {
    errors.password_confirmation = 'required'
  } else if (input.password !== input.password_confirmation) {
    errors.password_confirmation = 'confirmed'
  }

  return errors
}

export interface TenantWizardFieldErrors {
  tenant_code?: string
  name?: string
  locale?: string
  timezone?: string
  status?: string
  contact_email?: string
  mail_from_address?: string
  owner_name?: string
  owner_email?: string
  temporary_password?: string
  reason?: string
  new_owner_id?: string
}

const TENANT_CODE_RE = /^[a-z0-9]+(?:-[a-z0-9]+)*$/

export function validateTenantWizardStep(
  step: 1 | 2 | 3,
  form: {
    tenant_code: string
    name: string
    locale: string
    timezone: string
    status: 'active' | 'pending'
    contact_email: string
    mail_from_address: string
    owner_name: string
    owner_email: string
    send_invite: boolean
    temporary_password: string
  },
): TenantWizardFieldErrors {
  const errors: TenantWizardFieldErrors = {}

  if (step === 1) {
    const code = form.tenant_code.trim().toLowerCase()
    if (!code) {
      errors.tenant_code = 'required'
    } else if (!TENANT_CODE_RE.test(code)) {
      errors.tenant_code = 'tenantCode'
    }

    if (!form.name.trim()) {
      errors.name = 'required'
    }

    if (!form.locale.trim()) {
      errors.locale = 'required'
    }

    if (!form.timezone.trim()) {
      errors.timezone = 'required'
    }

    if (form.status !== 'active' && form.status !== 'pending') {
      errors.status = 'required'
    }

    if (form.contact_email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.contact_email.trim())) {
      errors.contact_email = 'email'
    }
  }

  if (step === 2) {
    if (!form.owner_name.trim()) {
      errors.owner_name = 'required'
    }

    if (!form.owner_email.trim()) {
      errors.owner_email = 'required'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.owner_email.trim())) {
      errors.owner_email = 'email'
    }

    if (!form.send_invite) {
      if (!form.temporary_password) {
        errors.temporary_password = 'required'
      } else if (form.temporary_password.length < 8) {
        errors.temporary_password = 'min'
      } else if (
        !/[A-Za-z]/.test(form.temporary_password) ||
        !/\d/.test(form.temporary_password)
      ) {
        errors.temporary_password = 'policy'
      }
    }
  }

  if (step === 3) {
    if (
      form.mail_from_address.trim() &&
      !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.mail_from_address.trim())
    ) {
      errors.mail_from_address = 'email'
    }
  }

  return errors
}

export function validateLifecycleReason(reason: string): TenantWizardFieldErrors {
  const errors: TenantWizardFieldErrors = {}
  const trimmed = reason.trim()
  if (!trimmed) {
    errors.reason = 'required'
  } else if (trimmed.length < 3) {
    errors.reason = 'reasonMin'
  }
  return errors
}

export function tenantStatusBadgeClass(status: string): string {
  switch (status) {
    case 'active':
      return 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200'
    case 'pending':
      return 'bg-amber-50 text-amber-900 ring-1 ring-amber-200'
    case 'suspended':
      return 'bg-orange-50 text-orange-900 ring-1 ring-orange-200'
    case 'archived':
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-neutral-200'
    default:
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-neutral-200'
  }
}
