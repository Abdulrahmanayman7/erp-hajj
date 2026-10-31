<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Lock,
  Pencil,
  Plus,
  RotateCcw,
} from 'lucide-vue-next'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { ApiError } from '@/shared/api/http'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useToast } from '@/shared/composables/useToast'

import SeasonFormDrawer from '../components/SeasonFormDrawer.vue'
import {
  useActivateSeasonMutation,
  useCloseSeasonMutation,
  useCreateSeasonMutation,
  useReopenSeasonMutation,
  useUpdateSeasonMutation,
} from '../mutations/useSeasonMutations'
import { useSeasonsQuery } from '../queries/useSeasonsQuery'
import type { Season, SeasonFormState, SeasonStatus } from '../types/seasons'
import {
  canActivateSeason,
  canCloseSeason,
  canEditSeason,
  canReopenSeason,
  emptySeasonForm,
  resolveSeasonsListState,
  statusBadgeClass,
  validateSeasonForm,
} from '../validation/seasonValidation'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const seasonStore = useAccommodationSeasonStore()

const filters = reactive({
  status: 'all' as SeasonStatus | 'all',
  page: 1,
  per_page: 15,
})

const queryParams = computed(() => ({
  status: filters.status,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch } = useSeasonsQuery(queryParams)
const seasons = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveSeasonsListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: seasons.value.length,
  }),
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: 'all', label: t('seasons.filters.allStatuses') },
  { value: 'draft', label: t('seasons.status.draft') },
  { value: 'active', label: t('seasons.status.active') },
  { value: 'closed', label: t('seasons.status.closed') },
])

const createMutation = useCreateSeasonMutation()
const updateMutation = useUpdateSeasonMutation()
const activateMutation = useActivateSeasonMutation()
const closeMutation = useCloseSeasonMutation()
const reopenMutation = useReopenSeasonMutation()

const drawerOpen = ref(false)
const editing = ref<Season | null>(null)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<SeasonFormState>(emptySeasonForm())
const isFormSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)
const formLocked = computed(() => editing.value != null && !canEditSeason(editing.value))

watch(
  () => filters.status,
  () => {
    filters.page = 1
  },
)

function resetFilters(): void {
  filters.status = 'all'
}

function openCreate(): void {
  editing.value = null
  Object.assign(form, emptySeasonForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  drawerOpen.value = true
}

function openEdit(season: Season): void {
  editing.value = season
  Object.assign(form, {
    code: season.code,
    name: season.name,
    hajj_year: String(season.hajj_year),
    operational_starts_on: season.operational_starts_on,
    operational_ends_on: season.operational_ends_on,
    notes: season.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  drawerOpen.value = true
}

async function submitForm(): Promise<void> {
  if (formLocked.value) return
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  formError.value = ''
  const local = validateSeasonForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length > 0) return

  const payload = {
    code: form.code.trim(),
    name: form.name.trim(),
    hajj_year: Number(form.hajj_year),
    operational_starts_on: form.operational_starts_on,
    operational_ends_on: form.operational_ends_on,
    notes: form.notes.trim() || null,
  }

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({ id: editing.value.id, payload })
      toast.success(t('seasons.toasts.updated'))
    } else {
      await createMutation.mutateAsync(payload)
      toast.success(t('seasons.toasts.created'))
    }
    drawerOpen.value = false
  } catch (error) {
    if (error instanceof ApiError) {
      if (error.status === 422 && error.errors) {
        formError.value = Object.values(error.errors)[0]?.[0] ?? error.message
        return
      }
      formError.value = error.message || t('seasons.errors.generic')
      return
    }
    formError.value = t('seasons.errors.generic')
  }
}

function selectOperational(season: Season): void {
  seasonStore.setSelectedSeasonId(season.id)
  toast.success(t('seasons.toasts.selected'))
}

async function onActivate(season: Season): Promise<void> {
  const ok = await confirm({
    title: t('seasons.confirmActivateTitle'),
    message: t('seasons.confirmActivateBody'),
    confirmLabel: t('seasons.confirmActivateCta'),
    cancelLabel: t('seasons.cancel'),
    variant: 'primary',
  })
  if (!ok) return
  try {
    await activateMutation.mutateAsync(season.id)
    toast.success(t('seasons.toasts.activated'))
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : t('seasons.errors.generic'))
  }
}

async function onClose(season: Season): Promise<void> {
  const ok = await confirm({
    title: t('seasons.confirmCloseTitle'),
    message: t('seasons.confirmCloseBody'),
    confirmLabel: t('seasons.confirmCloseCta'),
    cancelLabel: t('seasons.cancel'),
    variant: 'warning',
  })
  if (!ok) return
  try {
    await closeMutation.mutateAsync(season.id)
    toast.success(t('seasons.toasts.closed'))
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : t('seasons.errors.generic'))
  }
}

async function onReopen(season: Season): Promise<void> {
  const reason = window.prompt(t('seasons.reopenReasonPlaceholder'))
  if (reason == null) return
  if (!reason.trim()) {
    toast.error(t('seasons.errors.reopenReason'))
    return
  }
  const ok = await confirm({
    title: t('seasons.confirmReopenTitle'),
    message: t('seasons.confirmReopenBody'),
    confirmLabel: t('seasons.confirmReopenCta'),
    cancelLabel: t('seasons.cancel'),
    variant: 'warning',
  })
  if (!ok) return
  try {
    await reopenMutation.mutateAsync({ id: season.id, reason: reason.trim() })
    toast.success(t('seasons.toasts.reopened'))
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : t('seasons.errors.generic'))
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('seasons.title')"
      :subtitle="t('seasons.subtitle')"
      :meta="meta ? t('seasons.total', { count: meta.total }) : undefined"
    >
      <template #actions>
        <PermissionGuard permission="seasons.create">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white sm:w-auto"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            <span>{{ t('seasons.add') }}</span>
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <AppMobileFilters
      :show-search="false"
      :active-count="filters.status !== 'all' ? 1 : 0"
      @reset="resetFilters"
    >
      <template #desktop>
        <div
          class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4"
        >
          <AppSelect v-model="filters.status" :options="statusOptions" />
          <p
            v-if="seasonStore.selectedSeasonId"
            class="text-xs text-brand-text-muted"
          >
            {{ t('seasons.operationalSelected') }}: #{{ seasonStore.selectedSeasonId }}
          </p>
        </div>
      </template>
      <template #filters>
        <AppSelect v-model="filters.status" :options="statusOptions" />
      </template>
    </AppMobileFilters>

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('seasons.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('seasons.errors.load') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('seasons.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center"
    >
      <p class="text-sm text-brand-text-muted">{{ t('seasons.empty') }}</p>
      <PermissionGuard permission="seasons.create">
        <button
          type="button"
          class="mt-4 inline-flex h-10 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('seasons.add') }}
        </button>
      </PermissionGuard>
    </div>
    <template v-else>
      <div class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface lg:block">
        <table class="min-w-full text-sm">
          <thead class="bg-[#F4F6F5]">
            <tr>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('seasons.columns.code') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('seasons.columns.name') }}
              </th>
              <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
                {{ t('seasons.columns.hajjYear') }}
              </th>
              <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
                {{ t('seasons.columns.window') }}
              </th>
              <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
                {{ t('seasons.columns.status') }}
              </th>
              <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
                {{ t('seasons.columns.actions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="season in seasons"
              :key="season.id"
              class="border-t border-brand-border/80"
              :class="
                seasonStore.selectedSeasonId === season.id ? 'bg-emerald-50/40' : 'bg-brand-surface'
              "
            >
              <td class="px-5 py-3.5 font-mono text-xs" dir="ltr">{{ season.code }}</td>
              <td class="px-5 py-3.5 font-semibold">{{ season.name }}</td>
              <td class="px-5 py-3.5 text-center" dir="ltr">{{ season.hajj_year }}</td>
              <td class="px-5 py-3.5 text-center text-xs text-brand-text-secondary" dir="ltr">
                {{ season.operational_starts_on }} → {{ season.operational_ends_on }}
              </td>
              <td class="px-5 py-3.5 text-center">
                <span
                  class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                  :class="statusBadgeClass(season.status)"
                >
                  {{ t(`seasons.status.${season.status}`) }}
                </span>
              </td>
              <td class="px-5 py-3.5">
                <div class="flex flex-wrap items-center justify-center gap-1">
                  <button
                    type="button"
                    class="inline-flex h-9 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-brand-text-secondary hover:bg-brand-bg"
                    @click="selectOperational(season)"
                  >
                    <CheckCircle2 class="h-3.5 w-3.5" />
                    {{ t('seasons.selectOperational') }}
                  </button>
                  <PermissionGuard permission="seasons.update">
                    <button
                      type="button"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-brand-bg disabled:opacity-40"
                      :disabled="!canEditSeason(season)"
                      :title="
                        canEditSeason(season) ? t('seasons.editTitle') : t('seasons.closedLocked')
                      "
                      @click="openEdit(season)"
                    >
                      <Pencil v-if="canEditSeason(season)" class="h-4 w-4" />
                      <Lock v-else class="h-4 w-4" />
                    </button>
                  </PermissionGuard>
                  <PermissionGuard permission="seasons.activate">
                    <button
                      v-if="canActivateSeason(season)"
                      type="button"
                      class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-50"
                      @click="onActivate(season)"
                    >
                      {{ t('seasons.activate') }}
                    </button>
                  </PermissionGuard>
                  <PermissionGuard permission="seasons.close">
                    <button
                      v-if="canCloseSeason(season)"
                      type="button"
                      class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold text-amber-800 hover:bg-amber-50"
                      @click="onClose(season)"
                    >
                      {{ t('seasons.close') }}
                    </button>
                  </PermissionGuard>
                  <PermissionGuard permission="seasons.reopen">
                    <button
                      v-if="canReopenSeason(season)"
                      type="button"
                      class="inline-flex h-9 items-center gap-1 rounded-lg px-2 text-xs font-semibold hover:bg-brand-bg"
                      @click="onReopen(season)"
                    >
                      <RotateCcw class="h-3.5 w-3.5" />
                      {{ t('seasons.reopen') }}
                    </button>
                  </PermissionGuard>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="space-y-3 lg:hidden">
        <article
          v-for="season in seasons"
          :key="`m-${season.id}`"
          class="rounded-2xl border border-brand-border bg-brand-surface p-4"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <h3 class="font-semibold">{{ season.name }}</h3>
              <p class="mt-1 font-mono text-xs text-brand-text-muted" dir="ltr">
                {{ season.code }} · {{ season.hajj_year }}
              </p>
            </div>
            <span
              class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
              :class="statusBadgeClass(season.status)"
            >
              {{ t(`seasons.status.${season.status}`) }}
            </span>
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <button
              type="button"
              class="inline-flex h-9 items-center rounded-xl border border-brand-border px-3 text-xs font-semibold"
              @click="selectOperational(season)"
            >
              {{ t('seasons.selectOperational') }}
            </button>
            <PermissionGuard permission="seasons.update">
              <button
                type="button"
                class="inline-flex h-9 items-center rounded-xl border border-brand-border px-3 text-xs font-semibold disabled:opacity-40"
                :disabled="!canEditSeason(season)"
                @click="openEdit(season)"
              >
                {{ t('seasons.editTitle') }}
              </button>
            </PermissionGuard>
          </div>
        </article>
      </div>

      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3 rounded-2xl border border-brand-border bg-brand-surface px-4 py-3"
      >
        <button
          type="button"
          class="inline-flex h-10 items-center gap-1 rounded-xl border border-brand-border px-3 text-sm font-semibold disabled:opacity-40"
          :disabled="filters.page <= 1"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" />
          {{ t('seasons.prev') }}
        </button>
        <span class="text-sm text-brand-text-muted">{{ filters.page }} / {{ meta.last_page }}</span>
        <button
          type="button"
          class="inline-flex h-10 items-center gap-1 rounded-xl border border-brand-border px-3 text-sm font-semibold disabled:opacity-40"
          :disabled="filters.page >= meta.last_page"
          @click="filters.page += 1"
        >
          {{ t('seasons.next') }}
          <ChevronLeft class="h-4 w-4" />
        </button>
      </div>
    </template>

    <SeasonFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      :locked="formLocked"
      @close="drawerOpen = false"
      @update:form="Object.assign(form, $event)"
      @submit="submitForm"
    />
  </div>
</template>
