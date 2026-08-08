import { apiGet, apiPost, ensureCsrfCookie } from '@/shared/api/http'

import type {
  AuthUser,
  ForgotPasswordPayload,
  LoginPayload,
  ResetPasswordPayload,
} from '../types/auth'

export async function bootstrapCsrf(): Promise<void> {
  await ensureCsrfCookie()
}

export async function login(payload: LoginPayload): Promise<AuthUser> {
  const response = await apiPost<AuthUser>('/api/v1/auth/login', payload)
  return response.data
}

export async function logout(): Promise<void> {
  await apiPost<null>('/api/v1/auth/logout')
}

export async function fetchCurrentUser(): Promise<AuthUser> {
  const response = await apiGet<AuthUser>('/api/v1/auth/me')
  return response.data
}

export async function forgotPassword(payload: ForgotPasswordPayload): Promise<string> {
  const response = await apiPost<null>('/api/v1/auth/forgot-password', payload)
  return response.message
}

export async function resetPassword(payload: ResetPasswordPayload): Promise<string> {
  const response = await apiPost<null>('/api/v1/auth/reset-password', payload)
  return response.message
}
