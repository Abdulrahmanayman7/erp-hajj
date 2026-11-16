import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  ATTESTATION_PATH,
  attestationRequestHeaders,
  evaluateAttestation,
  isDisposableUatDatabase,
} from './uat-attestation.mjs'

describe('gps hub uat attestation', () => {
  it('accepts disposable erp_hajj_*_uat names only', () => {
    assert.equal(isDisposableUatDatabase('erp_hajj_gps_uat'), true)
    assert.equal(isDisposableUatDatabase('erp_hajj'), false)
    assert.equal(isDisposableUatDatabase('production'), false)
  })

  it('refuses token headers outside attestation path', () => {
    const api = 'http://127.0.0.1:8000'
    assert.deepEqual(
      attestationRequestHeaders(`${api}/api/v1/gps/providers`, api, 'tok'),
      {},
    )
    assert.deepEqual(
      attestationRequestHeaders(`${api}${ATTESTATION_PATH}`, api, 'tok'),
      { 'X-Browser-Uat-Token': 'tok' },
    )
  })

  it('accepts matching disposable attestation payload', () => {
    const verdict = evaluateAttestation(
      {
        success: true,
        data: { attestation: 'browser_uat', database: 'erp_hajj_gps_uat' },
      },
      { ok: true, status: 200, expectedDatabase: 'erp_hajj_gps_uat' },
    )
    assert.equal(verdict.ok, true)
    assert.equal(verdict.database, 'erp_hajj_gps_uat')
  })
})
