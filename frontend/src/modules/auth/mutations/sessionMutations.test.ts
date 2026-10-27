import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { useLoginMutation } from './useLoginMutation'
import { useLogoutMutation } from './useLogoutMutation'
import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { sessionTransition } from '@/shared/api/sessionLifetime'
import type { AuthUser } from '../types/auth'

const mocks = vi.hoisted(() => ({ login: vi.fn(), logout: vi.fn(), replace: vi.fn(async () => undefined) }))
vi.mock('../api/authApi', () => ({ login: mocks.login, logout: mocks.logout }))
vi.mock('vue-router', () => ({ useRouter: () => ({ replace: mocks.replace, currentRoute: { value: { query: {} } } }) }))
const apps: ReturnType<typeof createApp>[] = []
afterEach(() => { apps.splice(0).forEach(app => app.unmount()); vi.clearAllMocks(); sessionTransition.value = false })
function mountHook<T>(hook: () => T) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const pinia = createPinia(); setActivePinia(pinia)
  let result!: T
  const app = createApp({ setup() { result = hook(); return () => null } })
  app.use(pinia); app.use(VueQueryPlugin, { queryClient: client }); app.mount(document.createElement('div')); apps.push(app)
  return { result, client }
}
describe('authentication mutation boundaries', () => {
  it.each([[10, 20], [10, 10]])('switches tenant %i to %i without retaining the old account cache', async (oldTenant, nextTenant) => {
    const { result, client } = mountHook(useLoginMutation)
    client.setQueryData(['auth', 'me'], { id: 1, tenant: { id: oldTenant }, permissions: ['manage'] })
    client.setQueryData(['users'], ['old private names'])
    useAccommodationSeasonStore().setSelectedSeasonId(99)
    const user = { id: 2, tenant: { id: nextTenant }, permissions: ['view'], is_platform_user: false } as AuthUser
    mocks.login.mockResolvedValue(user)
    await result.mutateAsync({ email: 'new@example.test', password: 'test' })
    expect(client.getQueryData(['users'])).toBeUndefined()
    expect(client.getQueryData(['auth', 'me'])).toEqual(user)
    expect(useAccommodationSeasonStore().selectedSeasonId).toBeNull()
    expect(sessionTransition.value).toBe(false)
    expect(mocks.replace).toHaveBeenCalledWith('/app')
    client.clear()
  })
  it('evicts data before logout completes and remains locally signed out on failure', async () => {
    const { result, client } = mountHook(useLogoutMutation)
    client.setQueryData(['users'], ['private'])
    mocks.logout.mockImplementation(async () => { expect(client.getQueryData(['users'])).toBeUndefined(); throw new Error('offline') })
    await expect(result.mutateAsync()).rejects.toThrow('offline')
    expect(client.getQueryData(['auth', 'me'])).toBeNull()
    expect(mocks.replace).toHaveBeenCalledWith({ name: 'login' })
    expect(sessionTransition.value).toBe(false)
    client.clear()
  })
})
