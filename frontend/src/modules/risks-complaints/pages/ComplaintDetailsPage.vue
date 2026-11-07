<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { ArrowRight } from 'lucide-vue-next'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import SeasonWorkspaceBar from '@/modules/season-pilgrims/components/SeasonWorkspaceBar.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'

import ComplaintLifecycleActions from '../components/ComplaintLifecycleActions.vue'
import ComplaintNotesPanel from '../components/ComplaintNotesPanel.vue'
import OperationalTimelinePanel from '../components/OperationalTimelinePanel.vue'
import { useComplaintQuery } from '../queries/useComplaintsQuery'
import { complaintStatusBadgeClass } from '../validation/complaintValidation'
import { COMPLAINT_STATUSES } from '../types/complaints'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const store = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(store)

const complaintId = computed(() => {
  const raw = Number(route.params.id)
  return Number.isFinite(raw) && raw > 0 ? raw : null
})

const { data: complaint, isLoading, isError, refetch } = useComplaintQuery(
  complaintId,
  selectedSeasonId,
)

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
  <div class="space-y-6" data-testid="complaint-details-page" dir="rtl">
    <AppPageHeader
      :title="complaint?.title ?? t('complaints.detailsTitle')"
      :subtitle="complaint?.complaint_number ?? t('complaints.subtitle')"
    >
      <template #actions>
        <button
          type="button"
          class="inline-flex h-11 items-center gap-2 rounded-xl border border-brand-border px-4 text-sm font-semibold"
          @click="router.push('/app/complaints')"
        >
          <ArrowRight class="h-4 w-4" />
          {{ t('complaints.backToList') }}
        </button>
      </template>
    </AppPageHeader>

    <SeasonWorkspaceBar />

    <div
      v-if="selectedSeasonId == null"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('complaints.needSeason') }}
    </div>
    <div
      v-else-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('complaints.loading') }}
    </div>
    <div
      v-else-if="isError || !complaint"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('complaints.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('complaints.retry') }}
      </button>
    </div>

    <template v-else>
      <div class="grid gap-6 xl:grid-cols-[1fr_280px]">
        <div class="space-y-6">
          <section class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <div class="flex flex-wrap items-center gap-2">
              <span
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="complaintStatusBadgeClass(complaint.status)"
              >
                {{ t(`complaints.status.${complaint.status}`) }}
              </span>
              <span class="text-xs text-brand-text-muted">
                {{ t(`complaints.category.${complaint.category_code}`, complaint.category_code) }}
              </span>
            </div>

            <ol class="mt-4 flex flex-wrap gap-2" aria-label="status stepper">
              <li
                v-for="step in COMPLAINT_STATUSES"
                :key="step"
                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="
                  step === complaint.status
                    ? 'bg-brand-primary-dark text-white'
                    : 'bg-brand-bg text-brand-text-muted'
                "
              >
                {{ t(`complaints.status.${step}`) }}
              </li>
            </ol>

            <p class="mt-4 whitespace-pre-wrap text-sm text-brand-text">{{ complaint.body }}</p>

            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-brand-text-muted">{{ t('complaints.fields.assignee') }}</dt>
                <dd class="font-semibold">{{ complaint.assignee_employee?.full_name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-brand-text-muted">{{ t('complaints.columns.escalation') }}</dt>
                <dd class="font-semibold">{{ complaint.escalation_level }}</dd>
              </div>
              <div v-if="complaint.escalate_reason">
                <dt class="text-brand-text-muted">{{ t('complaints.fields.escalateReason') }}</dt>
                <dd>{{ complaint.escalate_reason }}</dd>
              </div>
              <div v-if="complaint.resolution_text">
                <dt class="text-brand-text-muted">{{ t('complaints.fields.resolutionText') }}</dt>
                <dd class="whitespace-pre-wrap">{{ complaint.resolution_text }}</dd>
              </div>
              <div v-if="complaint.resolved_at">
                <dt class="text-brand-text-muted">{{ t('complaints.fields.resolvedAt') }}</dt>
                <dd>{{ formatWhen(complaint.resolved_at) }}</dd>
              </div>
              <div v-if="complaint.close_reason">
                <dt class="text-brand-text-muted">{{ t('complaints.fields.closeReason') }}</dt>
                <dd>{{ complaint.close_reason }}</dd>
              </div>
              <div v-if="complaint.closed_at">
                <dt class="text-brand-text-muted">{{ t('complaints.fields.closedAt') }}</dt>
                <dd>{{ formatWhen(complaint.closed_at) }}</dd>
              </div>
              <div v-if="complaint.reopen_reason">
                <dt class="text-brand-text-muted">{{ t('complaints.fields.reopenReason') }}</dt>
                <dd>{{ complaint.reopen_reason }}</dd>
              </div>
            </dl>
          </section>

          <ComplaintNotesPanel :complaint="complaint" @refreshed="() => refetch()" />
          <OperationalTimelinePanel entity="complaint" :entity-id="complaint.id" />
        </div>

        <aside class="space-y-4">
          <h3 class="text-sm font-bold text-brand-text">{{ t('complaints.tabs.actions') }}</h3>
          <ComplaintLifecycleActions :complaint="complaint" @refreshed="() => refetch()" />
        </aside>
      </div>
    </template>
  </div>
</template>
