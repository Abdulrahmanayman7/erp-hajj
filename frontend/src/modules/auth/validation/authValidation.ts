export interface FieldErrors {
  email?: string
  password?: string
  password_confirmation?: string
}

export function validateLogin(email: string, password: string): FieldErrors {
  const errors: FieldErrors = {}

  if (!email.trim()) {
    errors.email = 'required'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
    errors.email = 'email'
  }

  if (!password) {
    errors.password = 'required'
  }

  return errors
}

export function validateForgotPassword(email: string): FieldErrors {
  const errors: FieldErrors = {}

  if (!email.trim()) {
    errors.email = 'required'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
    errors.email = 'email'
  }

  return errors
}

export function validateResetPassword(
  password: string,
  passwordConfirmation: string,
): FieldErrors {
  const errors: FieldErrors = {}

  if (!password) {
    errors.password = 'required'
  } else if (password.length < 8) {
    errors.password = 'min'
  } else if (!/[A-Za-z]/.test(password) || !/\d/.test(password)) {
    errors.password = 'policy'
  }

  if (!passwordConfirmation) {
    errors.password_confirmation = 'required'
  } else if (password !== passwordConfirmation) {
    errors.password_confirmation = 'confirmed'
  }

  return errors
}
