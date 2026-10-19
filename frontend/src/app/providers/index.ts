import { VueQueryPlugin } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import type { App } from 'vue'
import { createI18n } from 'vue-i18n'

import { accommodationAr } from '@/locales/accommodationAr'
import { ar } from '@/locales/ar'
import { autofocusWhen } from '@/shared/directives/autofocusWhen'

import { queryClient, router } from '../router'

const arMessages = {
  ...ar,
  nav: {
    ...ar.nav,
    ...accommodationAr.nav,
  },
  accommodation: accommodationAr.accommodation,
}

export const i18n = createI18n({
  legacy: false,
  locale: 'ar',
  fallbackLocale: 'ar',
  messages: { ar: arMessages },
})

export function installProviders(app: App): void {
  app.use(createPinia())
  app.use(router)
  app.use(VueQueryPlugin, { queryClient })
  app.use(i18n)
  app.directive('autofocus-when', autofocusWhen)
}
