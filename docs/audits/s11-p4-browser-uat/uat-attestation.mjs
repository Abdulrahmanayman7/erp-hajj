/**
 * Live Browser UAT attestation helpers (no network).
 * Database identity must come from the API attestation payload — never from env alone.
 */

export const DISPOSABLE_UAT_DB = /^erp_hajj_.+_uat$/

export function isDisposableUatDatabase(name) {
  return typeof name === 'string' && DISPOSABLE_UAT_DB.test(name)
}

/**
 * @param {unknown} payload JSON body from GET /api/v1/browser-uat/attestation
 * @param {{ expectedDatabase?: string | null, status?: number, ok?: boolean }} meta
 */
export function evaluateAttestation(payload, meta = {}) {
  const status = meta.status ?? 0
  const ok = meta.ok ?? false

  if (!ok || status < 200 || status >= 300) {
    return {
      ok: false,
      reason: `Attestation HTTP failed (status=${status}). Fail closed.`,
      database: null,
    }
  }

  if (!payload || typeof payload !== 'object') {
    return { ok: false, reason: 'Attestation response was not JSON object. Fail closed.', database: null }
  }

  if (payload.success !== true) {
    return {
      ok: false,
      reason: `Attestation success=false (code=${payload.code ?? 'unknown'}). Fail closed.`,
      database: null,
    }
  }

  const data = payload.data
  if (!data || typeof data !== 'object') {
    return { ok: false, reason: 'Attestation missing data object. Fail closed.', database: null }
  }

  if (data.attestation !== 'browser_uat') {
    return { ok: false, reason: 'Attestation marker missing. Fail closed.', database: null }
  }

  const database = data.database
  if (typeof database !== 'string' || database.trim() === '') {
    return { ok: false, reason: 'Attestation missing database identity. Fail closed.', database: null }
  }

  if (!isDisposableUatDatabase(database)) {
    return {
      ok: false,
      reason: `Attested database [${database}] is not a disposable erp_hajj_*_uat name. Fail closed.`,
      database,
    }
  }

  const expected = meta.expectedDatabase
  if (typeof expected === 'string' && expected.trim() !== '' && expected !== database) {
    return {
      ok: false,
      reason:
        `UAT_DATABASE=[${expected}] does not match attested database=[${database}]. ` +
        `Env is never proof of safety. Fail closed.`,
      database,
    }
  }

  return { ok: true, reason: null, database }
}
