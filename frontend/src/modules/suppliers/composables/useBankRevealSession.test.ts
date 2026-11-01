import { createApp, defineComponent, h, nextTick, ref } from 'vue'
import { afterEach, describe, expect, it } from 'vitest'

import type { BankAccountRevealPayload } from '../types/banking'
import { useBankRevealSession } from './useBankRevealSession'

const cleanups: Array<() => void> = []
afterEach(() => cleanups.splice(0).forEach((fn) => fn()))

function samplePayload(
  overrides: Partial<BankAccountRevealPayload> = {},
): BankAccountRevealPayload {
  return {
    id: 10,
    supplier_id: 1,
    account_key: 'ak',
    version_no: 1,
    status: 'verified',
    iban: 'SA0380000000608010167519',
    account_number: null,
    iban_last4: '7519',
    account_last4: null,
    ...overrides,
  }
}

function mountSession(options?: {
  supplierId?: number
  accountId?: number | null
  canReveal?: boolean
}) {
  const supplierId = ref(options?.supplierId ?? 1)
  const accountId = ref<number | null>(options?.accountId ?? 10)
  const canReveal = ref(options?.canReveal ?? true)
  let session!: ReturnType<typeof useBankRevealSession>

  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp(
    defineComponent({
      setup() {
        session = useBankRevealSession({
          supplierId,
          accountId,
          canReveal,
        })
        return () => h('div')
      },
    }),
  )
  app.mount(host)
  cleanups.push(() => {
    app.unmount()
    host.remove()
  })

  return { session, supplierId, accountId, canReveal, unmount: () => app.unmount() }
}

describe('useBankRevealSession', () => {
  it('accepts authorized reveal and clears plaintext on close', () => {
    const { session } = mountSession()
    session.begin(10)
    const token = session.startRequest()
    const payload = samplePayload()
    expect(
      session.accept(token, payload, { supplierId: 1, accountId: 10 }),
    ).toBe(true)
    expect(session.revealed.value?.iban).toContain('SA038')
    expect(payload.iban).toBeNull()

    session.close()
    expect(session.open.value).toBe(false)
    expect(session.revealed.value).toBeNull()
    expect(session.reason.value).toBe('')
  })

  it('rejects stale response after modal close', () => {
    const { session } = mountSession()
    session.begin(10)
    const token = session.startRequest()
    session.close()
    const payload = samplePayload()
    expect(session.accept(token, payload, { supplierId: 1, accountId: 10 })).toBe(false)
    expect(session.revealed.value).toBeNull()
    expect(payload.iban).toBeNull()
  })

  it('rejects stale response after supplier switch', async () => {
    const { session, supplierId } = mountSession()
    session.begin(10)
    const token = session.startRequest()
    supplierId.value = 2
    await nextTick()
    const payload = samplePayload()
    expect(session.accept(token, payload, { supplierId: 1, accountId: 10 })).toBe(false)
    expect(session.revealed.value).toBeNull()
  })

  it('rejects stale response after account switch', async () => {
    const { session, accountId } = mountSession()
    session.begin(10)
    const token = session.startRequest()
    accountId.value = 11
    await nextTick()
    const payload = samplePayload()
    expect(session.accept(token, payload, { supplierId: 1, accountId: 10 })).toBe(false)
    expect(session.revealed.value).toBeNull()
  })

  it('rejects stale response after permission loss', async () => {
    const { session, canReveal } = mountSession()
    session.begin(10)
    const token = session.startRequest()
    canReveal.value = false
    await nextTick()
    const payload = samplePayload()
    expect(session.accept(token, payload, { supplierId: 1, accountId: 10 })).toBe(false)
    expect(session.revealed.value).toBeNull()
  })

  it('rejects stale response after unmount', () => {
    const { session, unmount } = mountSession()
    session.begin(10)
    const token = session.startRequest()
    unmount()
    const payload = samplePayload()
    expect(session.accept(token, payload, { supplierId: 1, accountId: 10 })).toBe(false)
    expect(session.revealed.value).toBeNull()
  })

  it('prevents older request from overwriting a newer result', () => {
    const { session } = mountSession()
    session.begin(10)
    const oldToken = session.startRequest()
    const newToken = session.startRequest()

    const newerPayload = samplePayload({
      iban: 'SA4420000001234567891234',
      iban_last4: '1234',
    })
    expect(
      session.accept(newToken, newerPayload, { supplierId: 1, accountId: 10 }),
    ).toBe(true)
    expect(session.revealed.value?.iban).toBe('SA4420000001234567891234')

    const olderPayload = samplePayload({ iban: 'SA0380000000608010167519' })
    expect(
      session.accept(oldToken, olderPayload, { supplierId: 1, accountId: 10 }),
    ).toBe(false)
    expect(session.revealed.value?.iban).toBe('SA4420000001234567891234')
  })

  it('does not let stale finishRequest clear a newer pending marker', () => {
    const { session } = mountSession()
    session.begin(10)
    const oldToken = session.startRequest()
    const newToken = session.startRequest()
    expect(session.pendingGeneration.value).toBe(newToken)
    session.finishRequest(oldToken)
    expect(session.pendingGeneration.value).toBe(newToken)
    expect(session.isRequestCurrent(oldToken)).toBe(false)
    expect(session.isRequestCurrent(newToken)).toBe(true)
  })

  it('simulates deferred close-before-resolve without restoring plaintext', async () => {
    const { session } = mountSession()
    session.begin(10)
    const token = session.startRequest()

    let resolve!: (value: BankAccountRevealPayload) => void
    const deferred = new Promise<BankAccountRevealPayload>((res) => {
      resolve = res
    })

    const consume = deferred.then((payload) =>
      session.accept(token, payload, { supplierId: 1, accountId: 10 }),
    )

    session.close()
    resolve(samplePayload())
    await expect(consume).resolves.toBe(false)
    expect(session.revealed.value).toBeNull()
  })

  it('never writes plaintext to web storage', () => {
    const { session } = mountSession()
    session.begin(10)
    const token = session.startRequest()
    session.accept(
      token,
      samplePayload({ account_number: 'secret-local' }),
      { supplierId: 1, accountId: 10 },
    )
    expect(localStorage.length).toBe(0)
    expect(sessionStorage.length).toBe(0)
    expect(JSON.stringify(localStorage)).not.toContain('SA038')
    expect(JSON.stringify(sessionStorage)).not.toContain('secret-local')
  })
})
