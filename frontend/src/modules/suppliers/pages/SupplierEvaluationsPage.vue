<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { ClipboardList, Plus, Search } from 'lucide-vue-next'

import { listSupplierCategories } from '../api/categoriesApi'
import { listSuppliers } from '../api/suppliersApi'
import {
  useApproveEvaluationMutation,
  useCreateEvaluationMutation,
  useRejectEvaluationMutation,
  useSubmitEvaluationMutation,
} from '../mutations/useEvaluationMutations'
import { useEvaluationsQuery } from '../queries/useEvaluationsQuery'
import type { SupplierCategory } from '../types/categories'
import type { EvaluationKind, EvaluationStatus } from '../types/evaluations'
import type { Supplier } from '../types/suppliers'
import {
  evaluationKindOptions,
  mapEvaluationErrorCode,
  validateCreateEvaluation,
} from '../validation/evaluationValidation'
import { listSeasons } from '@/modules/season-pilgrims/api/seasonsApi'
import type { Season } from '@/modules/season-pilgrims/types/seasons'
import {
  resolvePurchaseListState,
  softStatusBadgeClass,
  softStatusDotClass,
} from '@/modules/purchase-requests/validation/purchaseRequestValidation'
import { ApiError } from '@/shared/api/http'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { namedCodeOption, toSelectId, toSelectNullableId } from '@/shared/lookups/selectOptions'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const { can } = usePermissions()

const filters = reactive({
  supplier_id: route.query.supplier_id ? Number(route.query.supplier_id) : ('' as number | ''),
  status: '' as EvaluationStatus | '',
  evaluation_kind: '' as EvaluationKind | '',
  q: '',
  page: 1,
  per_page: 20,
})

const committedSearch = useDebouncedRef(() => filters.q)
const queryParams = computed(() => ({
  supplier_id: filters.supplier_id === '' ? null : filters.supplier_id,
  status: filters.status || undefined,
  evaluation_kind: filters.evaluation_kind || undefined,
  q: committedSearch.value || undefined,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch, isFetching } = useEvaluationsQuery(queryParams)
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta ?? {})
const listState = computed(() =>
  resolvePurchaseListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: rows.value.length,
  }),
)

const createOpen = ref(false)
const rejectOpen = ref(false)
const rejectTargetId = ref<number | null>(null)
const rejectReason = ref('')
const form = reactive({
  supplier_id: '' as number | '',
  supplier_category_id: '' as number | '',
  evaluation_kind: 'qualification' as EvaluationKind,
  season_id: null as number | null,
  monitoring_recommended: false,
  notes: '',
})
const formError = ref('')

const createMutation = useCreateEvaluationMutation()
const submitMutation = useSubmitEvaluationMutation()
const approveMutation = useApproveEvaluationMutation()
const rejectMutation = useRejectEvaluationMutation()

const statusFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('common.all') },
  { value: 'draft', label: t('evaluations.statuses.draft') },
  { value: 'pending_approval', label: t('evaluations.statuses.pending_approval') },
  { value: 'approved', label: t('evaluations.statuses.approved') },
  { value: 'rejected', label: t('evaluations.statuses.rejected') },
])

const kindFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('common.all') },
  ...evaluationKindOptions.map((opt) => ({ value: opt.value, label: t(opt.labelKey) })),
])

const kindFormOptions = computed<AppSelectOption[]>(() =>
  evaluationKindOptions.map((opt) => ({ value: opt.value, label: t(opt.labelKey) })),
)

const emptySupplier = computed<AppSelectOption>(() => ({
  value: '',
  label: t('evaluations.filters.supplier'),
}))

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.status) count += 1
  if (filters.evaluation_kind) count += 1
  if (filters.supplier_id !== '') count += 1
  return count
})

const fetchSuppliers = (params: { search?: string; page: number; per_page: number }) =>
  listSuppliers({ ...params, q: params.search })

const fetchCategories = (params: { search?: string; page: number; per_page: number }) =>
  listSupplierCategories({ ...params, is_active: true })

const fetchSeasons = (params: { search?: string; page: number; per_page: number }) =>
  listSeasons({ page: params.page, per_page: params.per_page, status: 'all' })

function mapSupplierOption(supplier: Supplier): AppSelectOption {
  return {
    value: supplier.id,
    label: supplier.display_name,
    hint: supplier.supplier_number,
  }
}

function mapSeasonOption(season: Season): AppSelectOption {
  return {
    value: season.id,
    label: season.name,
    hint: season.code,
  }
}

function resetFilters(): void {
  filters.status = ''
  filters.evaluation_kind = ''
  filters.supplier_id = ''
}

watch(
  () => route.query.supplier_id,
  (value) => {
    filters.supplier_id = value ? Number(value) : ''
    if (value) form.supplier_id = Number(value)
  },
)

watch(
  () => [committedSearch.value, filters.status, filters.evaluation_kind, filters.supplier_id],
  () => {
    filters.page = 1
  },
)

async function createDraft(): Promise<void> {
  formError.value = ''
  const payload = {
    supplier_id: Number(form.supplier_id),
    supplier_category_id: Number(form.supplier_category_id),
    evaluation_kind: form.evaluation_kind,
    season_id: form.season_id,
    monitoring_recommended: form.monitoring_recommended,
    notes: form.notes || null,
  }
  const errors = validateCreateEvaluation(payload)
  if (Object.keys(errors).length) {
    formError.value = t('evaluations.errors.validation')
    return
  }
  try {
    const created = await createMutation.mutateAsync(payload)
    createOpen.value = false
    toast.success(t('evaluations.created'))
    await router.push(`/app/supplier-evaluations/${created.id}`)
  } catch (error) {
    const code = error instanceof ApiError ? error.code : undefined
    formError.value = t(mapEvaluationErrorCode(code))
  }
}

async function runSubmit(id: number): Promise<void> {
  try {
    await submitMutation.mutateAsync(id)
    toast.success(t('evaluations.submitted'))
    await refetch()
  } catch (error) {
    const code = error instanceof ApiError ? error.code : undefined
    toast.error(t(mapEvaluationErrorCode(code)))
  }
}

async function runApprove(id: number): Promise<void> {
  try {
    await approveMutation.mutateAsync({ id })
    toast.success(t('evaluations.approved'))
    await refetch()
  } catch (error) {
    const code = error instanceof ApiError ? error.code : undefined
    toast.error(t(mapEvaluationErrorCode(code)))
  }
}

function openReject(id: number): void {
  rejectTargetId.value = id
  rejectReason.value = ''
  rejectOpen.value = true
}

async function confirmReject(): Promise<void> {
  if (!rejectTargetId.value || rejectReason.value.trim().length < 10) return
  try {
    await rejectMutation.mutateAsync({ id: rejectTargetId.value, reason: rejectReason.value.trim() })
    toast.success(t('evaluations.rejected'))
    rejectOpen.value = false
    await refetch()
  } catch (error) {
    const code = error instanceof ApiError ? error.code : undefined
    toast.error(t(mapEvaluationErrorCode(code)))
  }
}

function openCreate(): void {
  form.supplier_id = filters.supplier_id === '' ? '' : filters.supplier_id
  form.supplier_category_id = ''
  form.evaluation_kind = 'qualification'
  form.season_id = null
  form.monitoring_recommended = false
  form.notes = ''
  formError.value = ''
  createOpen.value = true
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader :title="t('evaluations.title')">
      <template #actions>
        <PermissionGuard permission="supplier_evaluations.prepare">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-3 py-2 text-sm font-semibold text-white"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            {{ t('evaluations.create') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <AppMobileFilters
      v-model:search="filters.q"
      :search-placeholder="t('evaluations.filters.q')"
      :active-count="activeFilterCount"
      @reset="resetFilters"
    >
      <template #desktop>
        <div
          class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
        >
          <div class="relative min-w-48 flex-1">
            <Search
              class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
              :stroke-width="1.75"
              aria-hidden="true"
            />
            <input
              v-model="filters.q"
              type="search"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
              :placeholder="t('evaluations.filters.q')"
            />
          </div>
          <AppSelect v-model="filters.status" :options="statusFilterOptions" />
          <AppSelect v-model="filters.evaluation_kind" :options="kindFilterOptions" />
          <AppRemoteSelect
            :model-value="filters.supplier_id"
            query-key="evaluation-filter-suppliers"
            :fetcher="fetchSuppliers"
            :map-option="mapSupplierOption"
            :empty-option="emptySupplier"
            clearable
            @update:model-value="filters.supplier_id = toSelectId($event)"
          />
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <AppSelect v-model="filters.status" :options="statusFilterOptions" />
          <AppSelect v-model="filters.evaluation_kind" :options="kindFilterOptions" />
          <AppRemoteSelect
            :model-value="filters.supplier_id"
            query-key="evaluation-filter-suppliers"
            :fetcher="fetchSuppliers"
            :map-option="mapSupplierOption"
            :empty-option="emptySupplier"
            clearable
            @update:model-value="filters.supplier_id = toSelectId($event)"
          />
        </div>
      </template>
    </AppMobileFilters>

    <div class="rounded-2xl border border-brand-border bg-brand-surface">
      <div v-if="listState === 'loading'" class="p-10 text-center text-sm text-brand-text-muted">
        {{ t('common.loading') }}
      </div>
      <div v-else-if="listState === 'error'" class="p-10 text-center">
        <p class="text-sm text-red-700">{{ t('evaluations.loadError') }}</p>
        <button
          type="button"
          class="mt-3 text-sm font-semibold text-brand-primary underline"
          @click="() => { void refetch() }"
        >
          {{ t('common.retry') }}
        </button>
      </div>
      <div v-else-if="listState === 'empty'" class="p-10 text-center text-sm text-brand-text-muted">
        <ClipboardList class="mx-auto mb-3 h-8 w-8 opacity-50" />
        {{ t('evaluations.empty') }}
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="border-b border-brand-border bg-brand-bg/50 text-start text-brand-text-secondary">
            <tr>
              <th class="px-4 py-3 font-semibold">{{ t('evaluations.columns.number') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('evaluations.columns.kind') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('evaluations.columns.status') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('evaluations.columns.score') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('evaluations.columns.version') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('evaluations.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.id"
              class="border-b border-brand-border/60 last:border-0"
            >
              <td class="px-4 py-3">
                <RouterLink
                  :to="`/app/supplier-evaluations/${row.id}`"
                  class="font-semibold text-brand-primary hover:underline"
                >
                  {{ row.evaluation_number }}
                </RouterLink>
                <p class="text-xs text-brand-text-muted">
                  {{ row.supplier?.display_name ?? `#${row.supplier_id}` }}
                </p>
              </td>
              <td class="px-4 py-3">{{ t(`evaluations.kinds.${row.evaluation_kind}`) }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="softStatusBadgeClass(row.status)"
                >
                  <span class="h-1.5 w-1.5 rounded-full" :class="softStatusDotClass(row.status)" />
                  {{ t(`evaluations.statuses.${row.status}`) }}
                </span>
              </td>
              <td class="px-4 py-3 font-bold">{{ row.total_score ?? '—' }}</td>
              <td class="px-4 py-3">v{{ row.version_no }}</td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-2">
                  <button
                    v-if="row.status === 'draft' && can('supplier_evaluations.prepare')"
                    type="button"
                    class="rounded-lg border border-brand-border px-2 py-1 text-xs font-semibold"
                    @click="runSubmit(row.id)"
                  >
                    {{ t('evaluations.submit') }}
                  </button>
                  <button
                    v-if="row.status === 'pending_approval' && can('supplier_evaluations.approve')"
                    type="button"
                    class="rounded-lg bg-brand-primary px-2 py-1 text-xs font-semibold text-white"
                    @click="runApprove(row.id)"
                  >
                    {{ t('evaluations.approve') }}
                  </button>
                  <button
                    v-if="row.status === 'pending_approval' && can('supplier_evaluations.approve')"
                    type="button"
                    class="rounded-lg border border-red-200 px-2 py-1 text-xs font-semibold text-red-700"
                    @click="openReject(row.id)"
                  >
                    {{ t('evaluations.reject') }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer
        v-if="meta.last_page && meta.last_page > 1"
        class="flex items-center justify-between border-t border-brand-border px-4 py-3 text-sm"
      >
        <span class="text-brand-text-muted">
          {{ t('common.pageOf', { page: meta.current_page ?? filters.page, total: meta.last_page }) }}
        </span>
        <div class="flex gap-2">
          <button
            type="button"
            class="rounded-lg border border-brand-border px-3 py-1 disabled:opacity-50"
            :disabled="filters.page <= 1 || isFetching"
            @click="filters.page -= 1"
          >
            {{ t('common.prev') }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-brand-border px-3 py-1 disabled:opacity-50"
            :disabled="filters.page >= meta.last_page || isFetching"
            @click="filters.page += 1"
          >
            {{ t('common.next') }}
          </button>
        </div>
      </footer>
      <p
        v-else-if="listState === 'ready'"
        class="border-t border-brand-border px-4 py-2 text-xs text-brand-text-muted"
      >
        {{ t('evaluations.pagination', { total: meta.total ?? rows.length }) }}
      </p>
    </div>

    <div
      v-if="createOpen"
      class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
      @click.self="createOpen = false"
    >
      <div class="w-full max-w-lg space-y-4 rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-xl">
        <h2 class="text-lg font-bold text-brand-text">{{ t('evaluations.create') }}</h2>

        <label class="block text-sm">
          <span class="mb-1 block text-brand-text-secondary">{{ t('evaluations.fields.supplier') }}</span>
          <AppRemoteSelect
            :model-value="form.supplier_id"
            query-key="evaluation-create-suppliers"
            :fetcher="fetchSuppliers"
            :map-option="mapSupplierOption"
            @update:model-value="form.supplier_id = toSelectId($event)"
          />
        </label>

        <label class="block text-sm">
          <span class="mb-1 block text-brand-text-secondary">{{ t('evaluations.fields.category') }}</span>
          <AppRemoteSelect
            :model-value="form.supplier_category_id"
            query-key="evaluation-create-categories"
            :fetcher="fetchCategories"
            :map-option="(row: SupplierCategory) => namedCodeOption(row)"
            @update:model-value="form.supplier_category_id = toSelectId($event)"
          />
        </label>

        <label class="block text-sm">
          <span class="mb-1 block text-brand-text-secondary">{{ t('evaluations.fields.kind') }}</span>
          <AppSelect v-model="form.evaluation_kind" :options="kindFormOptions" />
        </label>

        <label v-if="form.evaluation_kind === 'post_season'" class="block text-sm">
          <span class="mb-1 block text-brand-text-secondary">{{ t('evaluations.fields.season') }}</span>
          <AppRemoteSelect
            :model-value="form.season_id"
            query-key="evaluation-create-seasons"
            :fetcher="fetchSeasons"
            :map-option="mapSeasonOption"
            clearable
            @update:model-value="form.season_id = toSelectNullableId($event)"
          />
        </label>

        <label class="flex items-center gap-2 text-sm text-brand-text">
          <input v-model="form.monitoring_recommended" type="checkbox" class="rounded border-brand-border" />
          {{ t('evaluations.fields.monitoringProposed') }}
        </label>

        <p v-if="formError" class="text-sm text-red-700">{{ formError }}</p>

        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-xl px-4 py-2 text-sm" @click="createOpen = false">
            {{ t('common.cancel') }}
          </button>
          <button
            type="button"
            class="rounded-xl bg-brand-primary px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
            :disabled="createMutation.isPending.value"
            @click="createDraft"
          >
            {{ t('evaluations.create') }}
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="rejectOpen"
      class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
      @click.self="rejectOpen = false"
    >
      <div class="w-full max-w-md space-y-4 rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-xl">
        <h2 class="text-lg font-bold text-brand-text">{{ t('evaluations.rejectTitle') }}</h2>
        <label class="block text-sm">
          <span class="mb-1 block text-brand-text-secondary">{{ t('evaluations.rejectReasonLabel') }}</span>
          <textarea
            v-model="rejectReason"
            rows="4"
            class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
            :placeholder="t('evaluations.rejectReasonHint')"
          />
        </label>
        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-xl px-4 py-2 text-sm" @click="rejectOpen = false">
            {{ t('common.cancel') }}
          </button>
          <button
            type="button"
            class="rounded-xl bg-red-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
            :disabled="rejectReason.trim().length < 10 || rejectMutation.isPending.value"
            @click="confirmReject"
          >
            {{ t('evaluations.rejectConfirm') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
