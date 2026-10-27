import { expect, it } from 'vitest'
import { ApiError } from '@/shared/api/http'
import { positionLookupState } from './positionLookupState'
const base = { canRead: true, unitId: 1, loading: false, error: null, count: 0 }
it.each([
  [{ ...base, loading: true }, 'loading'], [{ ...base }, 'empty'], [{ ...base, unitId: null }, 'chooseUnit'],
  [{ ...base, canRead: false }, 'forbidden'], [{ ...base, error: new ApiError(403, undefined) }, 'forbidden'],
  [{ ...base, error: new ApiError(0, undefined) }, 'error'], [{ ...base, error: new ApiError(500, undefined) }, 'error'],
  [{ ...base, count: 2 }, 'ready'], [{ ...base, search: 'أحمد' }, 'noMatches'],
] as const)('distinguishes the actual position lookup state', (input, state) => expect(positionLookupState(input)).toBe(state))
