import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const htaccessPath = resolve(dirname(fileURLToPath(import.meta.url)), '../../../public/.htaccess')

describe('Hostinger co-located .htaccess', () => {
  const htaccess = readFileSync(htaccessPath, 'utf8')

  it('prefers Vue index.html over Laravel index.php', () => {
    expect(htaccess).toContain('DirectoryIndex index.html index.php')
  })

  it('routes Laravel API and Sanctum CSRF to index.php before the SPA fallback', () => {
    expect(htaccess).toMatch(/RewriteCond %\{REQUEST_URI\} \^\/api\/ \[NC,OR\]/)
    expect(htaccess).toMatch(/RewriteCond %\{REQUEST_URI\} \^\/sanctum\/ \[NC\]/)
    expect(htaccess).toMatch(/RewriteRule \^ index\.php \[L\]/)

    const apiIndex = htaccess.indexOf('RewriteCond %{REQUEST_URI} ^/api/')
    const spaIndex = htaccess.indexOf('RewriteRule ^ index.html [L]')
    expect(apiIndex).toBeGreaterThan(-1)
    expect(spaIndex).toBeGreaterThan(apiIndex)
  })

  it('falls unknown non-file routes back to the Vue SPA', () => {
    expect(htaccess).toContain('RewriteCond %{REQUEST_FILENAME} !-f')
    expect(htaccess).toContain('RewriteCond %{REQUEST_FILENAME} !-d')
    expect(htaccess).toContain('RewriteRule ^ index.html [L]')
  })

  it('preserves PWA Cache-Control and Service-Worker headers', () => {
    expect(htaccess).toContain('FilesMatch "^index\\.html$"')
    expect(htaccess).toContain('FilesMatch "^sw\\.js$"')
    expect(htaccess).toContain('FilesMatch "^manifest\\.webmanifest$"')
    expect(htaccess).toContain('Cache-Control "no-cache"')
    expect(htaccess).toContain('Cache-Control "no-cache, no-store, must-revalidate"')
    expect(htaccess).toContain('Service-Worker-Allowed "/"')
    expect(htaccess).toContain('SetEnvIf Request_URI "^/assets/" LONG_CACHE')
    expect(htaccess).toContain('SetEnvIf Request_URI "^/icons/" LONG_CACHE')
    expect(htaccess).toContain('max-age=31536000, immutable')
  })
})
