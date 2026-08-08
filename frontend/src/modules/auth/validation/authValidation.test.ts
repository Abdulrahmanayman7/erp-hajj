import { describe, expect, it } from 'vitest'

import { validateForgotPassword, validateLogin, validateResetPassword } from './authValidation'

describe('authValidation', () => {
  it('requires email and password on login', () => {
    expect(validateLogin('', '')).toEqual({ email: 'required', password: 'required' })
  })

  it('rejects invalid email format', () => {
    expect(validateLogin('bad', 'secret').email).toBe('email')
  })

  it('accepts a valid login payload', () => {
    expect(validateLogin('user@example.com', 'secret')).toEqual({})
  })

  it('validates forgot-password email', () => {
    expect(validateForgotPassword('')).toEqual({ email: 'required' })
    expect(validateForgotPassword('user@example.com')).toEqual({})
  })

  it('enforces reset password policy and confirmation', () => {
    expect(validateResetPassword('short', 'short').password).toBe('min')
    expect(validateResetPassword('lettersOnly', 'lettersOnly').password).toBe('policy')
    expect(validateResetPassword('Password1', 'Password2').password_confirmation).toBe('confirmed')
    expect(validateResetPassword('Password1', 'Password1')).toEqual({})
  })
})
