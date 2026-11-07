<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2 } from 'lucide-vue-next'

import { usePermissions } from '@/shared/composables/usePermissions'

import type { OperationalRisk } from '../types/risks'
import {
  canAssessRisk,
  previewAssessment,
  riskSeverityBadgeClass,
} from '../validation/riskValidation'

const props = defineProps<{
  risk: OperationalRisk
  submitting: boolean
}>()

const emit = defineEmits<{
  assess: [payload: { likelihood: number; impact: number }]
}>()

const { t } = useI18n()
const { can } = usePermissions()

const likelihood = ref(props.risk.likelihood ?? 3)
const impact = ref(props.risk.impact ?? 3)

watch(
  () => [props.risk.likelihood, props.risk.impact],
  () => {
    likelihood.value = props.risk.likelihood ?? likelihood.value
    impact.value = props.risk.impact ?? impact.value
  },
)

const preview = computed(() => previewAssessment(likelihood.value, impact.value))
const editable = computed(
  () => can('risks.assess') && canAssessRisk(props.risk.status),
)

function pick(kind: 'likelihood' | 'impact', value: number): void {
  if (!editable.value) return
  if (kind === 'likelihood') likelihood.value = value
  else impact.value = value
}

function submit(): void {
  if (!editable.value || preview.value.score == null) return
  emit('assess', { likelihood: likelihood.value, impact: impact.value })
}
</script>

<template>
  <section class="space-y-4 rounded-2xl border border-brand-border bg-brand-surface p-4" data-testid="risk-assessment-panel">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-base font-bold text-brand-text">{{ t('risks.tabs.assessment') }}</h3>
      <div v-if="preview.score != null" class="flex items-center gap-2 text-sm">
        <span class="font-semibold text-brand-text">
          {{ t('risks.fields.score') }}: {{ preview.score }}
        </span>
        <span
          class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
          :class="riskSeverityBadgeClass(preview.severity)"
        >
          {{ t(`risks.severity.${preview.severity}`) }}
        </span>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
      <div>
        <p class="mb-2 text-sm font-semibold text-brand-text">{{ t('risks.fields.likelihood') }}</p>
        <div class="flex flex-wrap gap-2" role="group" :aria-label="t('risks.fields.likelihood')">
          <button
            v-for="n in 5"
            :key="`l-${n}`"
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border text-sm font-bold transition disabled:opacity-50"
            :class="
              likelihood === n
                ? 'border-brand-primary bg-brand-primary-soft text-brand-primary-dark'
                : 'border-brand-border bg-brand-bg text-brand-text hover:bg-brand-primary-soft/50'
            "
            :disabled="!editable || submitting"
            :data-testid="`risk-likelihood-${n}`"
            @click="pick('likelihood', n)"
          >
            {{ n }}
          </button>
        </div>
      </div>
      <div>
        <p class="mb-2 text-sm font-semibold text-brand-text">{{ t('risks.fields.impact') }}</p>
        <div class="flex flex-wrap gap-2" role="group" :aria-label="t('risks.fields.impact')">
          <button
            v-for="n in 5"
            :key="`i-${n}`"
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border text-sm font-bold transition disabled:opacity-50"
            :class="
              impact === n
                ? 'border-brand-primary bg-brand-primary-soft text-brand-primary-dark'
                : 'border-brand-border bg-brand-bg text-brand-text hover:bg-brand-primary-soft/50'
            "
            :disabled="!editable || submitting"
            :data-testid="`risk-impact-${n}`"
            @click="pick('impact', n)"
          >
            {{ n }}
          </button>
        </div>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-[280px] border-collapse text-center text-xs" aria-hidden="true">
        <tbody>
          <tr v-for="row in 5" :key="`row-${row}`">
            <td
              v-for="col in 5"
              :key="`cell-${row}-${col}`"
              class="h-8 w-8 border border-brand-border"
              :class="{
                'ring-2 ring-brand-primary ring-inset': likelihood === col && impact === 6 - row,
                'bg-emerald-100': col * (6 - row) <= 4,
                'bg-amber-100': col * (6 - row) > 4 && col * (6 - row) <= 9,
                'bg-orange-100': col * (6 - row) > 9 && col * (6 - row) <= 15,
                'bg-red-100': col * (6 - row) > 15,
              }"
            >
              {{ col * (6 - row) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <button
      v-if="editable"
      type="button"
      class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
      :disabled="submitting || preview.score == null"
      data-testid="risk-assess-submit"
      @click="submit"
    >
      <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
      {{ t('risks.actions.assess') }}
    </button>
  </section>
</template>
