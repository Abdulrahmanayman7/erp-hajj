/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Base URL of the ERP Hajj backend API (e.g. http://localhost:8000). Empty = same origin. */
  readonly VITE_API_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
    layout?: 'guest' | 'app' | 'platform'
    permission?: string
    /** Allow access when the user has any of these permissions. */
    permissionsAny?: string[]
  }
}
