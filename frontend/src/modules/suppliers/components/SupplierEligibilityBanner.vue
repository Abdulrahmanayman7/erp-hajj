<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { CheckCircle2, ShieldAlert } from 'lucide-vue-next'

import type { EligibilityBannerState, SupplierEligibility } from '../types/eligibility'
import EligibilityReasonList from './EligibilityReasonList.vue'

const props = defineProps<{
  eligibility: SupplierEligibility | null | undefined
  loading: boolean
  error: boolean
  labels?: Record<string, string>
}>()

const { t } = useI18n()

const state = computed<EligibilityBannerState | null>(() => {
  if (!props.eligibility) return null
  return props.eligibility.eligible ? 'eligible' : 'ineligible'
})
</script>

<template>
  <div
    v-if="loading"
    class="rounded-2xl border border-brand-border bg-brand-bg/50 p-4 text-sm text-brand-text-muted"
  >
    {{ t('suppliers.eligibility.banner.loading') }}
  </div>
  <div v-else-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
    {{ t('suppliers.eligibility.banner.error') }}
  </div>
  <section
    v-else-if="state"
    class="rounded-2xl border p-4"
    :class="
      state === 'eligible'
        ? 'border-emerald-300 bg-emerald-50 text-emerald-950'
        : 'border-amber-300 bg-amber-50 text-amber-950'
    "
    role="status"
    data-testid="eligibility-banner"
    :data-state="state"
  >
    <div class="flex items-start gap-3">
      <CheckCircle2 v-if="state === 'eligible'" class="mt-0.5 h-5 w-5 shrink-0" />
      <ShieldAlert v-else class="mt-0.5 h-5 w-5 shrink-0" />
      <div class="min-w-0 flex-1">
        <h3 class="font-bold">
          {{
            state === 'eligible'
              ? t('suppliers.eligibility.banner.eligibleTitle')
              : t('suppliers.eligibility.banner.ineligibleTitle')
          }}
        </h3>
        <p class="mt-1 text-sm">
          {{
            state === 'eligible'
              ? t('suppliers.eligibility.banner.eligibleBody')
              : t('suppliers.eligibility.banner.ineligibleBody')
          }}
        </p>
        <div v-if="state === 'ineligible' && eligibility && eligibility.reasons.length > 0" class="mt-3">
          <p class="mb-1 text-xs font-semibold">{{ t('suppliers.eligibility.banner.reasonsHeading') }}</p>
          <EligibilityReasonList :reasons="eligibility.reasons" :labels="labels" />
        </div>
        <p v-if="eligibility?.config" class="mt-3 text-xs opacity-80">
          {{
            t('suppliers.eligibility.banner.config', {
              key: eligibility.config.config_key,
              version: eligibility.config.version_no,
            })
          }}
        </p>
      </div>
    </div>
  </section>
</template>
