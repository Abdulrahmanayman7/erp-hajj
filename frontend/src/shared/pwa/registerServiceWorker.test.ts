import { describe, expect, it } from 'vitest'

import { PWA_SERVICE_WORKER_PATH } from './pwaVersion'
import { SERVICE_WORKER_REGISTER_OPTIONS } from './registerServiceWorker'

describe('service worker registration options', () => {
  it('disables updateViaCache so sw.js is not served from HTTP cache', () => {
    expect(PWA_SERVICE_WORKER_PATH).toBe('/sw.js')
    expect(SERVICE_WORKER_REGISTER_OPTIONS).toEqual({
      scope: '/',
      updateViaCache: 'none',
    })
  })
})
