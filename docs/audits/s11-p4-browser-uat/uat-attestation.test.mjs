import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { evaluateAttestation, isDisposableUatDatabase } from './uat-attestation.mjs'

describe('Browser UAT attestation evaluation', () => {
  it('rejects non-disposable attested databases even when UAT_DATABASE looks valid', () => {
    const verdict = evaluateAttestation(
      {
        success: true,
        data: {
          attestation: 'browser_uat',
          app_env: 'local',
          driver: 'mysql',
          database: 'erp_hajj',
        },
      },
      { ok: true, status: 200, expectedDatabase: 'erp_hajj_s11_uat' },
    )

    assert.equal(verdict.ok, false)
    assert.equal(verdict.database, 'erp_hajj')
    assert.match(verdict.reason, /not a disposable|does not match attested/i)
  })

  it('rejects when UAT_DATABASE mismatches a disposable attestation', () => {
    const verdict = evaluateAttestation(
      {
        success: true,
        data: {
          attestation: 'browser_uat',
          database: 'erp_hajj_s01_uat',
        },
      },
      { ok: true, status: 200, expectedDatabase: 'erp_hajj_s11_uat' },
    )

    assert.equal(verdict.ok, false)
    assert.match(verdict.reason, /UAT_DATABASE/)
  })

  it('never treats UAT_DATABASE alone as proof (missing attestation body)', () => {
    const verdict = evaluateAttestation(null, {
      ok: false,
      status: 404,
      expectedDatabase: 'erp_hajj_s11_uat',
    })
    assert.equal(verdict.ok, false)
    assert.match(verdict.reason, /Fail closed/)
  })

  it('rejects production-style unavailable attestation', () => {
    const verdict = evaluateAttestation(
      { success: false, code: 'BROWSER_UAT_ATTESTATION_UNAVAILABLE' },
      { ok: false, status: 404 },
    )
    assert.equal(verdict.ok, false)
  })

  it('rejects redirects and non-JSON', () => {
    assert.equal(evaluateAttestation(null, { ok: false, status: 302 }).ok, false)
    assert.equal(evaluateAttestation(null, { ok: true, status: 200 }).ok, false)
  })

  it('accepts a matching disposable attestation', () => {
    const verdict = evaluateAttestation(
      {
        success: true,
        data: { attestation: 'browser_uat', database: 'erp_hajj_s11_uat' },
      },
      { ok: true, status: 200, expectedDatabase: 'erp_hajj_s11_uat' },
    )
    assert.equal(verdict.ok, true)
    assert.equal(verdict.database, 'erp_hajj_s11_uat')
    assert.equal(isDisposableUatDatabase('erp_hajj'), false)
    assert.equal(isDisposableUatDatabase('erp_hajj_s11_uat'), true)
  })
})
