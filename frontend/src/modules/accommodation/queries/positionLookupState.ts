import { ApiError } from '@/shared/api/http'
export type PositionLookupState = 'loading' | 'empty' | 'chooseUnit' | 'forbidden' | 'error' | 'ready' | 'noMatches'
export function positionLookupState(input: { canRead: boolean; unitId: number | null; loading: boolean; error: unknown; count: number; search?: string }): PositionLookupState {
  if (!input.canRead || (input.error instanceof ApiError && input.error.status === 403)) return 'forbidden'
  if (!input.unitId) return 'chooseUnit'
  if (input.error) return 'error'
  if (input.loading) return 'loading'
  return input.count ? 'ready' : input.search?.trim() ? 'noMatches' : 'empty'
}
