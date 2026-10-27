import { createApp, defineComponent, h, nextTick, reactive } from 'vue'
import { createPinia } from 'pinia'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { createI18n } from 'vue-i18n'
import { beforeEach, expect, it, vi } from 'vitest'
import App from '@/App.vue'
import LoginPage from '../pages/LoginPage.vue'
import { ApiError } from '@/shared/api/http'
import { ar } from '@/locales/ar'
import { clearSession } from '../sessionIsolation'
import { useLogoutMutation } from './useLogoutMutation'
import { advanceSession, sessionGeneration, sessionTransition } from '@/shared/api/sessionLifetime'

const mocks = vi.hoisted(() => ({
  login: vi.fn(), logout: vi.fn(), replace: vi.fn(async (_to: unknown) => undefined),
  route: { meta: { layout: 'guest' }, query: {} },
}))
vi.mock('../api/authApi', () => ({ login: mocks.login, logout: mocks.logout }))
vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
  useRouter: () => ({ replace: mocks.replace, currentRoute: { value: { query: {} } } }),
  RouterLink: { template: '<a><slot /></a>' },
}))
vi.mock('@/app/layouts/AppLayout.vue', () => ({ default: { template: '<main><slot /></main>' } }))
vi.mock('@/app/layouts/GuestLayout.vue', () => ({ default: { template: '<main><slot /></main>' } }))
vi.mock('@/app/layouts/PlatformLayout.vue', () => ({ default: { template: '<main><slot /></main>' } }))

beforeEach(() => {
  vi.clearAllMocks()
  mocks.route = reactive({ meta: { layout: 'guest' }, query: {} })
  mocks.replace.mockImplementation(async (_to: unknown) => undefined)
  sessionTransition.value = false
})

it('preserves the login failure message when App handles the session boundary', async () => {
  let reject!: (reason: unknown) => void
  mocks.login.mockImplementation(() => new Promise((_resolve, rejectPromise) => { reject = rejectPromise }))
  const client = new QueryClient()
  const container = document.createElement('div')
  document.body.appendChild(container)
  const app = createApp(App)
  app.use(createPinia())
  app.use(VueQueryPlugin, { queryClient: client })
  app.use(createI18n({ legacy: false, locale: 'ar', messages: { ar } }))
  app.component('RouterView', defineComponent({ setup: () => () => h(LoginPage) }))
  app.mount(container)
  try {
    const email = container.querySelector('input[type="email"]') as HTMLInputElement
    const password = container.querySelector('input[type="password"]') as HTMLInputElement
    email.value = 'someone@example.test'; email.dispatchEvent(new Event('input', { bubbles: true }))
    password.value = 'wrong-password'; password.dispatchEvent(new Event('input', { bubbles: true }))
    await nextTick()
    container.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await vi.waitFor(() => expect(mocks.login).toHaveBeenCalledOnce())
    const generationDuringRequest = sessionGeneration.value
    reject(new ApiError(401, { success: false, message: 'invalid', code: 'AUTH_INVALID_CREDENTIALS' }))
    await vi.waitFor(() => expect(sessionTransition.value).toBe(false))
    expect(container.textContent).toContain(ar.auth.errors.invalidCredentials)
    // Neither starting the request nor receiving a 401 replaces the guest form.
    expect(container.querySelector('input[type="email"]')).toBe(email)
    expect(container.querySelector('input[type="password"]')).toBe(password)
    expect(email.value).toBe('someone@example.test')
    expect(sessionGeneration.value).toBe(generationDuringRequest)
    const signal = localStorage.getItem('erp-hajj.sessionBoundary') ?? ''
    expect(signal).not.toContain(email.value)
    expect(signal).not.toContain(password.value)
  } finally { app.unmount(); container.remove(); client.clear(); sessionTransition.value = false }
})


function mountLifecycleApp() {
  const client = new QueryClient()
  const mountedIdentities: unknown[] = []
  const PrivatePage = defineComponent({ setup() {
    const identity = client.getQueryData<{ name: string }>(['auth', 'me'])
    mountedIdentities.push(identity)
    const logout = useLogoutMutation()
    return () => h('section', [
      h('span', identity?.name ?? 'empty'),
      h('button', { onClick: () => logout.mutate() }, 'logout'),
    ])
  } })
  const container = document.createElement('div')
  document.body.appendChild(container)
  const app = createApp(App)
  app.use(createPinia())
  app.use(VueQueryPlugin, { queryClient: client })
  app.use(createI18n({ legacy: false, locale: 'ar', messages: { ar } }))
  app.component('RouterView', defineComponent({ setup: () => () =>
    h(mocks.route.meta.layout === 'guest' ? LoginPage : PrivatePage),
  }))
  app.mount(container)
  return { client, container, mountedIdentities,
    dispose: () => { app.unmount(); container.remove(); client.clear(); sessionTransition.value = false },
  }
}

it('navigates from the retained guest form into a fresh authenticated tree on successful login', async () => {
  const view = mountLifecycleApp()
  const user = { id: 2, name: 'New user', tenant: { id: 20 }, permissions: ['view'], is_platform_user: false }
  mocks.login.mockResolvedValue(user)
  mocks.replace.mockImplementation(async () => { mocks.route.meta.layout = 'app' })
  try {
    const email = view.container.querySelector('input[type="email"]') as HTMLInputElement
    const password = view.container.querySelector('input[type="password"]') as HTMLInputElement
    email.value = 'new@example.test'; email.dispatchEvent(new Event('input', { bubbles: true }))
    password.value = 'password'; password.dispatchEvent(new Event('input', { bubbles: true }))
    await nextTick()
    view.container.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await vi.waitFor(() => expect(view.container.textContent).toContain('New user'))
    expect(view.container.querySelector('form')).toBeNull()
    expect(view.client.getQueryData(['auth', 'me'])).toEqual(user)
    expect(view.mountedIdentities).toEqual([user])
  } finally { view.dispose() }
})

it('removes the authenticated page during logout and leaves only the guest form', async () => {
  mocks.route.meta.layout = 'app'
  const view = mountLifecycleApp()
  view.client.setQueryData(['auth', 'me'], { name: 'Previous private user' })
  advanceSession()
  await nextTick()
  let complete!: () => void
  mocks.logout.mockImplementation(() => new Promise<void>(resolve => { complete = resolve }))
  mocks.replace.mockImplementation(async () => { mocks.route.meta.layout = 'guest' })
  try {
    expect(view.container.textContent).toContain('Previous private user')
    view.container.querySelector('button')!.click()
    await vi.waitFor(() => expect(mocks.logout).toHaveBeenCalledOnce())
    expect(view.container.textContent).not.toContain('Previous private user')
    expect(view.container.querySelector('section')).toBeNull()
    expect(view.client.getQueryData(['auth', 'me'])).toBeUndefined()
    complete()
    await vi.waitFor(() => expect(view.container.querySelector('form')).not.toBeNull())
    expect(view.container.textContent).not.toContain('Previous private user')
    expect(view.client.getQueryData(['auth', 'me'])).toBeNull()
  } finally { view.dispose() }
})

it.each([[10, 20], [10, 10]])('disposes tenant %i private component state before displaying the next tenant %i', async (firstTenant, nextTenant) => {
  mocks.route.meta.layout = 'app'
  const view = mountLifecycleApp()
  const first = { id: 1, name: 'Private manager', tenant: { id: firstTenant }, permissions: ['manage'] }
  const second = { id: 2, name: 'Restricted viewer', tenant: { id: nextTenant }, permissions: ['view'] }
  try {
    view.client.setQueryData(['auth', 'me'], first)
    advanceSession(); await nextTick()
    expect(view.container.textContent).toContain(first.name)
    sessionTransition.value = true
    await clearSession(view.client)
    await nextTick()
    expect(view.container.textContent).not.toContain(first.name)
    expect(view.container.querySelector('section')).toBeNull()
    view.client.setQueryData(['auth', 'me'], second)
    sessionTransition.value = false
    await nextTick()
    expect(view.container.textContent).toContain(second.name)
    expect(view.container.textContent).not.toContain(first.name)
    expect(view.mountedIdentities.at(-1)).toEqual(second)
  } finally { view.dispose() }
})
