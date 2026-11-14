import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { classifyRequest } from './network-policy.mjs'

const FE = 'http://127.0.0.1:5173'

describe('Mock Browser UAT network policy', () => {
  it('mocks same-origin /api before any FE continue decision', () => {
    assert.equal(classifyRequest(`${FE}/api/v1/auth/me`, { feOrigin: FE }), 'mock-api')
    assert.equal(classifyRequest(`${FE}/api/v1/pilgrims`, { feOrigin: FE }), 'mock-api')
  })

  it('mocks same-origin /sanctum', () => {
    assert.equal(classifyRequest(`${FE}/sanctum/csrf-cookie`, { feOrigin: FE }), 'mock-api')
  })

  it('mocks Laravel :8000 API when VITE_API_URL is set', () => {
    assert.equal(
      classifyRequest('http://127.0.0.1:8000/api/v1/health', { feOrigin: FE }),
      'mock-api',
    )
  })

  it('denies unexpected external API origins (never allow-fe or mock-success)', () => {
    assert.equal(classifyRequest('https://evil.example/api/v1/secrets', { feOrigin: FE }), 'deny')
    assert.equal(classifyRequest('https://evil.example/steal', { feOrigin: FE }), 'deny')
  })

  it('allows FE assets on the local origin without requiring VITE_API_URL', () => {
    assert.equal(classifyRequest(`${FE}/src/main.ts`, { feOrigin: FE }), 'allow-fe')
    assert.equal(classifyRequest(`${FE}/@vite/client`, { feOrigin: FE }), 'allow-fe')
    assert.equal(classifyRequest(`${FE}/app/pilgrims`, { feOrigin: FE }), 'allow-fe')
  })

  it('denies redirect-style cross-origin non-FE targets', () => {
    assert.equal(
      classifyRequest('http://127.0.0.1:9999/api/v1/browser-uat/attestation', { feOrigin: FE }),
      'deny',
    )
    assert.equal(classifyRequest('http://malicious.local/callback', { feOrigin: FE }), 'deny')
  })

  it('never returns allow-fe for API or sanctum paths', () => {
    const samples = [
      `${FE}/api/v1/x`,
      `${FE}/sanctum/csrf-cookie`,
      'http://localhost:8000/api/v1/x',
      'https://other.test/api/v1/x',
    ]
    for (const url of samples) {
      assert.notEqual(classifyRequest(url, { feOrigin: FE }), 'allow-fe', url)
    }
  })

  it('proves deny decisions never map to network continue', () => {
    const denied = [
      'https://evil.example/api/v1/secrets',
      'http://127.0.0.1:9999/api/v1/x',
      'https://other.test/callback',
    ]
    for (const url of denied) {
      const d = classifyRequest(url, { feOrigin: FE })
      assert.equal(d, 'deny')
      assert.notEqual(d, 'allow-fe')
    }
  })
})
