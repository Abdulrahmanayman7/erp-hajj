<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { ChevronLeft, ChevronRight, Pencil, Plus } from 'lucide-vue-next'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import SeasonWorkspaceBar from '@/modules/season-pilgrims/components/SeasonWorkspaceBar.vue'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import SeasonReminderRuleFormDrawer from '../components/SeasonReminderRuleFormDrawer.vue'
import {
  useActivateSeasonReminderRuleMutation,
  useArchiveSeasonReminderRuleMutation,
  useCreateSeasonReminderRuleMutation,
  usePauseSeasonReminderRuleMutation,
  useUpdateSeasonReminderRuleMutation,
} from '../mutations/useSeasonReminderRulesMutations'
import { useSeasonReminderRulesQuery } from '../queries/useSeasonReminderRulesQuery'
import {
  REMINDER_RULE_STATUSES,
  type ListSeasonReminderRulesParams,
  type ReminderRuleFormState,
  type ReminderRuleStatus,
  type SeasonReminderRule,
} from '../types/seasonReminderRules'
import {
  buildCreatePayload,
  buildUpdatePayload,
  canActivateReminderRule,
  canArchiveReminderRule,
  canEditReminderRule,
  canManageReminderRules,
  canPauseReminderRule,
  computeFireDate,
  emptyReminderRuleForm,
  hasUnapprovedRecipientPermission,
  mapReminderErrorCode,
  reminderSeverityBadgeClass,
  reminderStatusBadgeClass,
  resolveReminderListState,
  ruleToForm,
  validateReminderRuleForm,
} from '../validation/seasonReminderRuleValidation'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const { can } = usePermissions()
const store = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(store)

const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const selectedSeason = computed(
  () => (seasonsQuery.data.value?.data ?? []).find((s) => s.id === selectedSeasonId.value) ?? null,
)
const seasonClosed = computed(() => selectedSeason.value?.status === 'closed')
const seasonNotActive = computed(() => selectedSeason.value != null && selectedSeason.value.status !== 'active')
const seasonStartsOn = computed(() => selectedSeason.value?.operational_starts_on ?? null)
const seasonLabel = computed(() =>
  selectedSeason.value ? `${selectedSeason.value.name} (${selectedSeason.value.code})` : '',
)
const canManage = computed(() => canManageReminderRules(can))
const canCreate = computed(() => canManage.value && selectedSeason.value != null && !seasonClosed.value)

const filters = reactive({ status: '' as ReminderRuleStatus | '', page: 1, per_page: 15 })
const params = computed<ListSeasonReminderRulesParams>(() => ({
  status: filters.status,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch } = useSeasonReminderRulesQuery(selectedSeasonId, params)
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveReminderListState({
    hasSeason: selectedSeasonId.value != null,
    isLoading: isLoading.value,
    isError: isError.value,
    count: rows.value.length,
  }),
)

watch(
  () => [filters.status, selectedSeasonId.value],
  () => {
    filters.page = 1
  },
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonalTemplates.reminders.allStatuses') },
  ...REMINDER_RULE_STATUSES.map((s) => ({ value: s, label: t(`seasonalTemplates.reminders.status.${s}`) })),
])

/* ----------------------------------------------------------------- drawer */
const drawerOpen = ref(false)
const editing = ref<SeasonReminderRule | null>(null)
const form = reactive<ReminderRuleFormState>(emptyReminderRuleForm())
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})

const createMutation = useCreateSeasonReminderRuleMutation()
const updateMutation = useUpdateSeasonReminderRuleMutation()
const activateMutation = useActivateSeasonReminderRuleMutation()
const pauseMutation = usePauseSeasonReminderRuleMutation()
const archiveMutation = useArchiveSeasonReminderRuleMutation()
const submitting = computed(() => createMutation.isPending.value || updateMutation.isPending.value)

function resetErrors(): void {
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
}

function openCreate(): void {
  editing.value = null
  Object.assign(form, emptyReminderRuleForm())
  resetErrors()
  drawerOpen.value = true
}

function openEdit(rule: SeasonReminderRule): void {
  editing.value = rule
  Object.assign(form, ruleToForm(rule))
  resetErrors()
  drawerOpen.value = true
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('seasonalTemplates.reminders.errors.generic')
  const mapped = mapReminderErrorCode(error.code)
  return mapped !== 'generic'
    ? t(`seasonalTemplates.reminders.errors.${mapped}`)
    : error.message || t('seasonalTemplates.reminders.errors.generic')
}

async function submit(): Promise<void> {
  if (!selectedSeasonId.value || seasonClosed.value) {
    formError.value = t('seasonalTemplates.reminders.errors.SEASON_REMINDER_SEASON_CLOSED')
    return
  }
  resetErrors()
  const local = validateReminderRuleForm(form, { editing: editing.value != null })
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return
  try {
    if (editing.value) {
      await updateMutation.mutateAsync({ id: editing.value.id, payload: buildUpdatePayload(form) })
      toast.success(t('seasonalTemplates.reminders.toasts.updated'))
    } else {
      await createMutation.mutateAsync({ seasonId: selectedSeasonId.value, payload: buildCreatePayload(form) })
      toast.success(t('seasonalTemplates.reminders.toasts.created'))
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

/* ----------------------------------------------------------------- lifecycle actions */
type RuleAction = 'activate' | 'resume' | 'pause' | 'archive'

async function runAction(rule: SeasonReminderRule, action: RuleAction): Promise<void> {
  const body =
    action === 'activate' || action === 'resume'
      ? seasonNotActive.value
        ? t(`seasonalTemplates.reminders.confirm.${action}.bodyInactiveSeason`)
        : t(`seasonalTemplates.reminders.confirm.${action}.body`)
      : t(`seasonalTemplates.reminders.confirm.${action}.body`)
  const ok = await confirm({
    title: t(`seasonalTemplates.reminders.confirm.${action}.title`),
    message: body,
    confirmLabel: t(`seasonalTemplates.reminders.actions.${action}`),
    cancelLabel: t('seasonalTemplates.common.cancel'),
    variant: action === 'archive' ? 'danger' : 'warning',
  })
  if (!ok) return
  try {
    if (action === 'activate' || action === 'resume') await activateMutation.mutateAsync(rule.id)
    else if (action === 'pause') await pauseMutation.mutateAsync(rule.id)
    else await archiveMutation.mutateAsync(rule.id)
    toast.success(t(`seasonalTemplates.reminders.toasts.${action}`))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

const actionBusy = computed(
  () => activateMutation.isPending.value || pauseMutation.isPending.value || archiveMutation.isPending.value,
)

/* ----------------------------------------------------------------- display */
function formatDate(date: string | null): string {
  if (!date) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', { dateStyle: 'medium' }).format(new Date(`${date}T00:00:00`))
  } catch {
    return date
  }
}

function triggerSummary(rule: SeasonReminderRule): string {
  if (rule.trigger_mode === 'absolute_date') return formatDate(rule.trigger_on)
  return t('seasonalTemplates.reminders.offsetSummary', { days: rule.offset_days ?? 0 })
}

function fireDate(rule: SeasonReminderRule): string {
  return formatDate(computeFireDate(rule, seasonStartsOn.value))
}

function recipientLabel(permission: string): string {
  const key = `seasonalTemplates.reminders.recipients.${permission}`
  return hasUnapprovedRecipientPermission({ recipient_permission: permission }) ? permission : t(key)
}
</script>

<template>
  <div class="space-y-6" data-testid="season-reminder-rules-page" dir="rtl">
    <AppPageHeader
      :title="t('seasonalTemplates.reminders.title')"
      :subtitle="t('seasonalTemplates.reminders.subtitle')"
      :meta="listState === 'ready' && meta ? t('seasonalTemplates.reminders.total', { count: meta.total }) : undefined"
    >
      <template #actions>
        <button
          v-if="canManage"
          type="button"
          class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
          :disabled="!canCreate"
          data-testid="reminder-create-btn"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('seasonalTemplates.reminders.add') }}
        </button>
      </template>
    </AppPageHeader>

    <SeasonWorkspaceBar />

    <div
      class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-start text-xs leading-relaxed text-amber-900"
      data-testid="reminder-safety-hint"
    >
      <p>{{ t('seasonalTemplates.reminders.safety.draft') }}</p>
      <p class="mt-1">{{ t('seasonalTemplates.reminders.safety.scanner') }}</p>
      <p v-if="seasonNotActive && !seasonClosed" class="mt-1 font-semibold" data-testid="reminder-season-not-active">
        {{ t('seasonalTemplates.reminders.safety.seasonNotActive') }}
      </p>
      <p v-if="seasonClosed" class="mt-1 font-semibold" data-testid="reminder-season-closed">
        {{ t('seasonalTemplates.reminders.safety.seasonClosed') }}
      </p>
    </div>

    <div
      v-if="selectedSeasonId != null"
      class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
    >
      <div class="w-full sm:w-52" data-testid="reminder-status-filter">
        <AppSelect v-model="filters.status" :options="statusOptions" />
      </div>
    </div>

    <div v-if="listState === 'need_season'" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted" data-testid="reminders-need-season">
      {{ t('seasonOps.bar.needSeason') }}
    </div>
    <div v-else-if="listState === 'loading'" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted" data-testid="reminders-loading">
      {{ t('seasonalTemplates.common.loading') }}
    </div>
    <div v-else-if="listState === 'error'" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center" data-testid="reminders-error">
      <p class="text-sm text-red-700">{{ t('seasonalTemplates.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('seasonalTemplates.common.retry') }}
      </button>
    </div>
    <div v-else-if="listState === 'empty'" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted" data-testid="reminders-empty">
      {{ t('seasonalTemplates.reminders.empty') }}
    </div>
    <div v-else class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface" data-testid="reminders-table">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-[#F4F6F5]">
            <tr>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.reminders.columns.code') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.reminders.columns.title') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.reminders.columns.severity') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.reminders.columns.status') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.reminders.columns.trigger') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.reminders.columns.fireDate') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.reminders.columns.recipients') }}</th>
              <th v-if="canManage" class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.reminders.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" class="border-t border-brand-border/80" :data-testid="`reminder-row-${row.id}`">
              <td class="px-5 py-3.5 text-start font-mono text-xs" dir="ltr">{{ row.code }}</td>
              <td class="px-5 py-3.5 text-start">
                <p class="font-semibold text-brand-text">{{ row.title }}</p>
                <p v-if="row.body" class="mt-0.5 line-clamp-2 text-xs text-brand-text-muted">{{ row.body }}</p>
              </td>
              <td class="px-5 py-3.5 text-start">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="reminderSeverityBadgeClass(row.severity)">
                  {{ t(`seasonalTemplates.reminders.severity.${row.severity}`) }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-start">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="reminderStatusBadgeClass(row.status)">
                  {{ t(`seasonalTemplates.reminders.status.${row.status}`) }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-start text-xs text-brand-text-secondary">
                <p>{{ t(`seasonalTemplates.reminders.triggerMode.${row.trigger_mode}`) }}</p>
                <p class="mt-0.5 text-brand-text-muted">{{ triggerSummary(row) }}</p>
              </td>
              <td class="px-5 py-3.5 text-start text-xs text-brand-text-secondary" data-testid="reminder-fire-date">{{ fireDate(row) }}</td>
              <td class="px-5 py-3.5 text-start text-xs text-brand-text-secondary">{{ recipientLabel(row.recipient_permission) }}</td>
              <td v-if="canManage" class="px-5 py-3.5">
                <div class="flex flex-wrap justify-center gap-1">
                  <button
                    v-if="canEditReminderRule(row.status, seasonClosed, can)"
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-brand-bg"
                    :aria-label="t('seasonalTemplates.reminders.actions.edit')"
                    :data-testid="`reminder-edit-${row.id}`"
                    @click="openEdit(row)"
                  >
                    <Pencil class="h-4 w-4" />
                  </button>
                  <button
                    v-if="canActivateReminderRule(row.status, seasonClosed, can)"
                    type="button"
                    class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold hover:bg-brand-bg disabled:opacity-40"
                    :disabled="actionBusy"
                    :data-testid="`reminder-activate-${row.id}`"
                    @click="runAction(row, row.status === 'paused' ? 'resume' : 'activate')"
                  >
                    {{ row.status === 'paused' ? t('seasonalTemplates.reminders.actions.resume') : t('seasonalTemplates.reminders.actions.activate') }}
                  </button>
                  <button
                    v-if="canPauseReminderRule(row.status, can)"
                    type="button"
                    class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold hover:bg-brand-bg disabled:opacity-40"
                    :disabled="actionBusy"
                    :data-testid="`reminder-pause-${row.id}`"
                    @click="runAction(row, 'pause')"
                  >
                    {{ t('seasonalTemplates.reminders.actions.pause') }}
                  </button>
                  <button
                    v-if="canArchiveReminderRule(row.status, can)"
                    type="button"
                    class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:opacity-40"
                    :disabled="actionBusy"
                    :data-testid="`reminder-archive-${row.id}`"
                    @click="runAction(row, 'archive')"
                  >
                    {{ t('seasonalTemplates.reminders.actions.archive') }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t border-brand-border px-5 py-3 text-sm">
        <button type="button" class="inline-flex h-9 items-center gap-1 rounded-lg px-3 disabled:opacity-40" :disabled="filters.page <= 1" :aria-label="t('common.prev')" @click="filters.page -= 1">
          <ChevronRight class="h-4 w-4" />
        </button>
        <span class="text-brand-text-muted">{{ filters.page }} / {{ meta.last_page }}</span>
        <button type="button" class="inline-flex h-9 items-center gap-1 rounded-lg px-3 disabled:opacity-40" :disabled="filters.page >= meta.last_page" :aria-label="t('common.next')" @click="filters.page += 1">
          <ChevronLeft class="h-4 w-4" />
        </button>
      </div>
    </div>

    <SeasonReminderRuleFormDrawer
      :open="drawerOpen"
      :editing="editing != null"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="submitting"
      :season-label="seasonLabel"
      :season-starts-on="seasonStartsOn"
      @close="drawerOpen = false"
      @submit="submit"
      @update:form="Object.assign(form, $event)"
    />
  </div>
</template>
