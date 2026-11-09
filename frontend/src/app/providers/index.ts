import { installSessionIsolation } from '@/modules/auth/sessionIsolation'
import { VueQueryPlugin } from '@tanstack/vue-query'
import { createPinia } from 'pinia'
import type { App } from 'vue'
import { createI18n } from 'vue-i18n'

import { accommodationAr } from '@/locales/accommodationAr'
import { ar } from '@/locales/ar'
import { executiveDashboardAr } from '@/locales/executiveDashboardAr'
import { operationalKpisAr } from '@/locales/operationalKpisAr'
import { risksComplaintsAr } from '@/locales/risksComplaintsAr'
import { seasonalTemplatesAr } from '@/locales/seasonalTemplatesAr'
import { seasonPilgrimsAr } from '@/locales/seasonPilgrimsAr'
import { seasonProjectsAr } from '@/locales/seasonProjectsAr'
import { transportationAr } from '@/locales/transportationAr'
import { autofocusWhen } from '@/shared/directives/autofocusWhen'

import { queryClient, router } from '../router'

const arMessages = {
  ...ar,
  nav: {
    ...ar.nav,
    ...accommodationAr.nav,
    ...seasonPilgrimsAr.nav,
    ...transportationAr.nav,
    ...seasonProjectsAr.nav,
    ...risksComplaintsAr.nav,
    ...seasonalTemplatesAr.nav,
    ...operationalKpisAr.nav,
    ...executiveDashboardAr.nav,
  },
  accommodation: accommodationAr.accommodation,
  pilgrims: seasonPilgrimsAr.pilgrims,
  seasons: seasonPilgrimsAr.seasons,
  seasonOps: seasonPilgrimsAr.seasonOps,
  transportation: transportationAr.transportation,
  seasonProjects: seasonProjectsAr.seasonProjects,
  risks: risksComplaintsAr.risks,
  complaints: risksComplaintsAr.complaints,
  seasonalTemplates: seasonalTemplatesAr.seasonalTemplates,
  operationalKpis: operationalKpisAr.operationalKpis,
  executiveDashboard: executiveDashboardAr.executiveDashboard,
}

export const i18n = createI18n({
  legacy: false,
  locale: 'ar',
  fallbackLocale: 'ar',
  messages: { ar: arMessages },
})

export function installProviders(app: App): void {
  app.use(createPinia())
  installSessionIsolation(queryClient)
  app.use(router)
  app.use(VueQueryPlugin, { queryClient })
  app.use(i18n)
  app.directive('autofocus-when', autofocusWhen)
}
