import { useI18n } from 'vue-i18n'

import { ApiError } from '@/shared/api/http'

import { mapAccommodationErrorCode } from '../validation/accommodationValidation'

export function useAccommodationErrors() {
  const { t } = useI18n()

  function message(error: unknown): string {
    if (!(error instanceof ApiError)) return t('accommodation.errors.generic')
    if (error.status === 404) return t('accommodation.errors.notFound')
    if (error.status === 403) return t('accommodation.errors.forbidden')
    const mapped = mapAccommodationErrorCode(error.code)
    if (mapped !== 'generic') return t(`accommodation.errors.${mapped}`)
    return error.message || t('accommodation.errors.generic')
  }

  return { message }
}
