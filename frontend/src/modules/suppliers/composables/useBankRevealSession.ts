import { onUnmounted, ref, toValue, watch, type Ref, type WatchSource } from 'vue'

import type { BankAccountRevealPayload } from '../types/banking'
import { scrubRevealPayload } from '../validation/bankingValidation'

export type BankRevealRequestSnapshot = {
  supplierId: number
  accountId: number
}

/**
 * Ephemeral plaintext reveal session with monotonic request generation.
 * Late responses must never restore plaintext after invalidation.
 * Never writes to localStorage/sessionStorage/IndexedDB or TanStack Query caches.
 */
export function useBankRevealSession(options: {
  supplierId: WatchSource<number | null | undefined>
  accountId: WatchSource<number | null | undefined>
  canReveal: WatchSource<boolean>
}) {
  const revealed: Ref<BankAccountRevealPayload | null> = ref(null)
  const open = ref(false)
  const reason = ref('')
  /** Monotonic generation — bumped on begin, startRequest, and every invalidation. */
  const generation = ref(0)
  const pendingGeneration = ref<number | null>(null)
  const mounted = ref(true)

  function clearPlaintext(): void {
    if (revealed.value) {
      scrubRevealPayload(revealed.value)
    }
    revealed.value = null
  }

  function bumpGeneration(): number {
    generation.value += 1
    pendingGeneration.value = null
    return generation.value
  }

  /** Invalidate outstanding requests and wipe sensitive state. Keeps open/reason unless closing. */
  function invalidateOutstanding(): void {
    bumpGeneration()
    clearPlaintext()
  }

  function close(): void {
    open.value = false
    reason.value = ''
    invalidateOutstanding()
  }

  function begin(_forAccountId: number): void {
    invalidateOutstanding()
    reason.value = ''
    open.value = true
    void _forAccountId
  }

  /**
   * Start a Reveal network request. Bumps generation so any older in-flight
   * response can no longer accept. Returns the token that must be passed to accept/finish.
   */
  function startRequest(): number {
    clearPlaintext()
    const token = bumpGeneration()
    pendingGeneration.value = token
    return token
  }

  function isRequestCurrent(token: number): boolean {
    return (
      mounted.value &&
      open.value &&
      Boolean(toValue(options.canReveal)) &&
      token === generation.value &&
      pendingGeneration.value === token
    )
  }

  function discardPayload(payload: BankAccountRevealPayload): void {
    payload.iban = null
    payload.account_number = null
  }

  /**
   * Accept plaintext only when the request generation is still current and
   * supplier/account/permission/session still match the submit snapshot.
   */
  function accept(
    token: number,
    payload: BankAccountRevealPayload,
    snapshot: BankRevealRequestSnapshot,
  ): boolean {
    const supplierId = toValue(options.supplierId)
    const accountId = toValue(options.accountId)

    const allowed =
      mounted.value &&
      open.value &&
      Boolean(toValue(options.canReveal)) &&
      token === generation.value &&
      pendingGeneration.value === token &&
      supplierId != null &&
      accountId != null &&
      supplierId === snapshot.supplierId &&
      accountId === snapshot.accountId &&
      payload.supplier_id === snapshot.supplierId &&
      payload.id === snapshot.accountId

    if (!allowed) {
      discardPayload(payload)
      return false
    }

    revealed.value = {
      id: payload.id,
      supplier_id: payload.supplier_id,
      account_key: payload.account_key,
      version_no: payload.version_no,
      status: payload.status,
      iban: payload.iban,
      account_number: payload.account_number,
      iban_last4: payload.iban_last4,
      account_last4: payload.account_last4,
    }
    // Scrub the inbound object after copy so callers holding the same reference lose plaintext.
    discardPayload(payload)
    return true
  }

  /** Clear pending marker for this token only — never disturb a newer request. */
  function finishRequest(token: number): void {
    if (pendingGeneration.value === token) {
      pendingGeneration.value = null
    }
  }

  watch(options.supplierId, () => close())
  watch(options.accountId, () => {
    if (open.value || pendingGeneration.value != null || revealed.value != null) {
      close()
    }
  })
  watch(options.canReveal, (allowed) => {
    if (!allowed) close()
  })

  onUnmounted(() => {
    mounted.value = false
    close()
  })

  return {
    open,
    reason,
    revealed,
    generation,
    pendingGeneration,
    begin,
    startRequest,
    accept,
    close,
    clearPlaintext,
    invalidateOutstanding,
    isRequestCurrent,
    finishRequest,
  }
}
