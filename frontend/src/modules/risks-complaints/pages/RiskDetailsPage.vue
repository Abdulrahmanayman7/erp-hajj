<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { ArrowRight } from 'lucide-vue-next'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import SeasonWorkspaceBar from '@/modules/season-pilgrims/components/SeasonWorkspaceBar.vue'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import { useToast } from '@/shared/composables/useToast'

import OperationalTimelinePanel from '../components/OperationalTimelinePanel.vue'
import RiskAssessmentPanel from '../components/RiskAssessmentPanel.vue'
import RiskLifecycleActions from '../components/RiskLifecycleActions.vue'
import RiskTreatmentForm from '../components/RiskTreatmentForm.vue'
import {
  useAssessRiskMutation,
  useUpsertRiskTreatmentMutation,
} from '../mutations/useRiskMutations'
import { useRiskQuery } from '../queries/useRisksQuery'
import type { TreatmentStatus } from '../types/risks'
import {
  mapRiskErrorCode,
  riskSeverityBadgeClass,
  riskStatusBadgeClass,
} from '../validation/riskValidation'

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const store = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(store)

const riskId = computed(() => {
  const raw = Number(route.params.id)
  return Number.isFinite(raw) && raw > 0 ? raw : null
})

const { data: risk, isLoading, isError, refetch } = useRiskQuery(riskId, selectedSeasonId)
const assessMutation = useAssessRiskMutation()
const treatmentMutation = useUpsertRiskTreatmentMutation()
const assessPending = computed(() => assessMutation.isPending.value)
const treatmentPending = computed(() => treatmentMutation.isPending.value)

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('risks.errors.generic')
  const mapped = mapRiskErrorCode(error.code)
  return mapped !== 'generic' ? t(`risks.errors.${mapped}`) : error.message || t('risks.errors.generic')
}

async function onAssess(payload: { likelihood: number; impact: number }): Promise<void> {
  if (riskId.value == null) return
  try {
    await assessMutation.mutateAsync({
      id: riskId.value,
      payload,
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('risks.toasts.assessed'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onTreatment(payload: {
  title: string
  plan_body: string
  owner_employee_id: number
  due_at: string | null
  status: TreatmentStatus
}): Promise<void> {
  if (riskId.value == null) return
  try {
    await treatmentMutation.mutateAsync({
      id: riskId.value,
      payload,
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('risks.toasts.treatmentSaved'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function formatWhen(iso: string | null | undefined): string {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', { dateStyle: 'medium', timeStyle: 'short' }).format(
      new Date(iso),
    )
  } catch {
    return iso
  }
}
</script>

<template>
  <div class="space-y-6" data-testid="risk-details-page" dir="rtl">
    <AppPageHeader
      :title="risk?.title ?? t('risks.detailsTitle')"
      :subtitle="risk?.risk_number ?? t('risks.subtitle')"
    >
      <template #actions>
        <button
          type="button"
          class="inline-flex h-11 items-center gap-2 rounded-xl border border-brand-border px-4 text-sm font-semibold"
          @click="router.push('/app/risks')"
        >
          <ArrowRight class="h-4 w-4" />
          {{ t('risks.backToList') }}
        </button>
      </template>
    </AppPageHeader>

    <SeasonWorkspaceBar />

    <div
      v-if="selectedSeasonId == null"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('risks.needSeason') }}
    </div>
    <div
      v-else-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('risks.loading') }}
    </div>
    <div
      v-else-if="isError || !risk"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('risks.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('risks.retry') }}
      </button>
    </div>

    <template v-else>
      <div class="grid gap-6 xl:grid-cols-[1fr_280px]">
        <div class="space-y-6">
          <section class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <div class="flex flex-wrap items-center gap-2">
              <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="riskStatusBadgeClass(risk.status)">
                {{ t(`risks.status.${risk.status}`) }}
              </span>
              <span
                v-if="risk.severity_level"
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="riskSeverityBadgeClass(risk.severity_level)"
              >
                {{ t(`risks.severity.${risk.severity_level}`) }}
              </span>
              <span v-if="risk.score != null" class="text-sm font-semibold text-brand-text">
                {{ t('risks.fields.score') }}: {{ risk.score }}
              </span>
            </div>
            <p v-if="risk.description" class="mt-4 whitespace-pre-wrap text-sm text-brand-text-secondary">
              {{ risk.description }}
            </p>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-brand-text-muted">{{ t('risks.fields.owner') }}</dt>
                <dd class="font-semibold">{{ risk.owner_employee?.full_name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-brand-text-muted">{{ t('risks.fields.createdBy') }}</dt>
                <dd class="font-semibold">{{ risk.created_by?.name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-brand-text-muted">{{ t('risks.fields.approvedAt') }}</dt>
                <dd>{{ formatWhen(risk.approved_at) }}</dd>
              </div>
              <div>
                <dt class="text-brand-text-muted">{{ t('risks.columns.updated') }}</dt>
                <dd>{{ formatWhen(risk.updated_at) }}</dd>
              </div>
              <div v-if="risk.closure_outcome">
                <dt class="text-brand-text-muted">{{ t('risks.fields.closureOutcome') }}</dt>
                <dd>{{ t(`risks.closure.${risk.closure_outcome}`) }}</dd>
              </div>
              <div v-if="risk.close_reason">
                <dt class="text-brand-text-muted">{{ t('risks.fields.closeReason') }}</dt>
                <dd>{{ risk.close_reason }}</dd>
              </div>
            </dl>
          </section>

          <RiskAssessmentPanel
            :risk="risk"
            :submitting="assessPending"
            @assess="onAssess"
          />
          <RiskTreatmentForm
            :risk="risk"
            :submitting="treatmentPending"
            @save="onTreatment"
          />
          <OperationalTimelinePanel entity="risk" :entity-id="risk.id" />
        </div>

        <aside class="space-y-4">
          <h3 class="text-sm font-bold text-brand-text">{{ t('risks.tabs.actions') }}</h3>
          <RiskLifecycleActions :risk="risk" @refreshed="() => refetch()" />
        </aside>
      </div>
    </template>
  </div>
</template>
