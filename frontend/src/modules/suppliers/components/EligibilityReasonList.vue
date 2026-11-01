<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import type { EligibilityReason } from '../types/eligibility'
import { eligibilityReasonKey } from '../validation/eligibilityValidation'

const props = withDefaults(
  defineProps<{
    reasons: EligibilityReason[]
    /** requirement_key → Arabic label (optional; falls back to the key). */
    labels?: Record<string, string>
  }>(),
  { labels: () => ({}) },
)

const { t } = useI18n()

function reasonText(reason: EligibilityReason): string {
  const base = t(`suppliers.eligibility.reasons.${eligibilityReasonKey(reason.code)}`)
  if (!reason.requirement_key) return base
  const requirement = props.labels[reason.requirement_key] ?? reason.requirement_key
  if (reason.expires_at) {
    return t('suppliers.eligibility.reasons.withExpiry', {
      reason: base,
      requirement,
      date: reason.expires_at,
    })
  }
  return t('suppliers.eligibility.reasons.withRequirement', { reason: base, requirement })
}
</script>

<template>
  <ul class="list-disc space-y-1 ps-5 text-sm">
    <li v-for="(reason, index) in reasons" :key="`${reason.code}-${reason.requirement_key ?? ''}-${index}`">
      {{ reasonText(reason) }}
    </li>
  </ul>
</template>
