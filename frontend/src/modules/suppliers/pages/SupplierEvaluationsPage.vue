<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { ClipboardList, Plus } from 'lucide-vue-next'

import {
  useApproveEvaluationMutation,
  useCreateEvaluationMutation,
  useRejectEvaluationMutation,
  useSubmitEvaluationMutation,
} from '../mutations/useEvaluationMutations'
import { useEvaluationsQuery } from '../queries/useEvaluationsQuery'
import type { EvaluationKind, EvaluationStatus } from '../types/evaluations'
import {
  evaluationKindOptions,
  mapEvaluationErrorCode,
  validateCreateEvaluation,
} from '../validation/evaluationValidation'
import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const { can } = usePermissions()

const filters = reactive({
  supplier_id: route.query.supplier_id ? Number(route.query.supplier_id) : null as number | null,
  status: '' as EvaluationStatus | '',
  evaluation_kind: '' as EvaluationKind | '',
  q: '',
  page: 1,
  per_page: 20,
})

const { data, isLoading, isError, refetch } = useEvaluationsQuery(filters)
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta ?? {})

const createOpen = ref(false)
const form = reactive({
  supplier_id: filters.supplier_id ?? 0,
  supplier_category_id: 0,
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

watch(
  () => route.query.supplier_id,
  (value) => {
    filters.supplier_id = value ? Number(value) : null
    form.supplier_id = filters.supplier_id ?? 0
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

async function runReject(id: number): Promise<void> {
  const reason = window.prompt(t('evaluations.rejectPrompt'))
  if (!reason || reason.trim().length < 10) return
  try {
    await rejectMutation.mutateAsync({ id, reason: reason.trim() })
    toast.success(t('evaluations.rejected'))
    await refetch()
  } catch (error) {
    const code = error instanceof ApiError ? error.code : undefined
    toast.error(t(mapEvaluationErrorCode(code)))
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <ClipboardList class="h-6 w-6 text-brand-primary" />
        <h1 class="text-xl font-extrabold text-brand-text">{{ t('evaluations.title') }}</h1>
      </div>
      <PermissionGuard permission="supplier_evaluations.prepare">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2 text-sm font-bold text-white"
          @click="createOpen = true"
        >
          <Plus class="h-4 w-4" />
          {{ t('evaluations.create') }}
        </button>
      </PermissionGuard>
    </div>

    <div class="grid gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 md:grid-cols-4">
      <label class="text-sm">
        <span class="mb-1 block text-brand-text-secondary">{{ t('evaluations.filters.q') }}</span>
        <input v-model="filters.q" class="w-full rounded-xl border border-brand-border px-3 py-2" />
      </label>
      <label class="text-sm">
        <span class="mb-1 block text-brand-text-secondary">{{ t('evaluations.filters.status') }}</span>
        <select v-model="filters.status" class="w-full rounded-xl border border-brand-border px-3 py-2">
          <option value="">{{ t('common.all') }}</option>
          <option value="draft">{{ t('evaluations.statuses.draft') }}</option>
          <option value="pending_approval">{{ t('evaluations.statuses.pending_approval') }}</option>
          <option value="approved">{{ t('evaluations.statuses.approved') }}</option>
          <option value="rejected">{{ t('evaluations.statuses.rejected') }}</option>
        </select>
      </label>
      <label class="text-sm">
        <span class="mb-1 block text-brand-text-secondary">{{ t('evaluations.filters.kind') }}</span>
        <select
          v-model="filters.evaluation_kind"
          class="w-full rounded-xl border border-brand-border px-3 py-2"
        >
          <option value="">{{ t('common.all') }}</option>
          <option v-for="opt in evaluationKindOptions" :key="opt.value" :value="opt.value">
            {{ t(opt.labelKey) }}
          </option>
        </select>
      </label>
      <label class="text-sm">
        <span class="mb-1 block text-brand-text-secondary">{{ t('evaluations.filters.supplierId') }}</span>
        <input
          v-model.number="filters.supplier_id"
          type="number"
          class="w-full rounded-xl border border-brand-border px-3 py-2"
        />
      </label>
    </div>

    <p v-if="isLoading" class="text-sm text-brand-text-muted">{{ t('common.loading') }}</p>
    <p v-else-if="isError" class="text-sm text-red-700">
      {{ t('evaluations.loadError') }}
      <button type="button" class="ms-2 underline" @click="refetch()">{{ t('common.retry') }}</button>
    </p>
    <p v-else-if="rows.length === 0" class="text-sm text-brand-text-muted">{{ t('evaluations.empty') }}</p>

    <div v-else class="overflow-x-auto rounded-2xl border border-brand-border bg-brand-surface">
      <table class="min-w-full text-sm">
        <thead class="bg-brand-bg/60 text-brand-text-secondary">
          <tr>
            <th class="px-4 py-3 text-start font-semibold">{{ t('evaluations.columns.number') }}</th>
            <th class="px-4 py-3 text-start font-semibold">{{ t('evaluations.columns.kind') }}</th>
            <th class="px-4 py-3 text-start font-semibold">{{ t('evaluations.columns.status') }}</th>
            <th class="px-4 py-3 text-start font-semibold">{{ t('evaluations.columns.score') }}</th>
            <th class="px-4 py-3 text-start font-semibold">{{ t('evaluations.columns.version') }}</th>
            <th class="px-4 py-3 text-start font-semibold">{{ t('evaluations.columns.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t border-brand-border">
            <td class="px-4 py-3">
              <RouterLink
                :to="`/app/supplier-evaluations/${row.id}`"
                class="font-semibold text-brand-primary"
              >
                {{ row.evaluation_number }}
              </RouterLink>
              <p class="text-xs text-brand-text-muted">
                {{ row.supplier?.display_name ?? `#${row.supplier_id}` }}
              </p>
            </td>
            <td class="px-4 py-3">{{ t(`evaluations.kinds.${row.evaluation_kind}`) }}</td>
            <td class="px-4 py-3">{{ t(`evaluations.statuses.${row.status}`) }}</td>
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
                  @click="runReject(row.id)"
                >
                  {{ t('evaluations.reject') }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="border-t border-brand-border px-4 py-2 text-xs text-brand-text-muted">
        {{ t('evaluations.pagination', { total: meta.total ?? rows.length }) }}
      </p>
    </div>

    <div
      v-if="createOpen"
      class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
      @click.self="createOpen = false"
    >
      <div class="w-full max-w-lg space-y-4 rounded-2xl bg-brand-surface p-5 shadow-xl">
        <h2 class="text-lg font-bold">{{ t('evaluations.create') }}</h2>
        <label class="block text-sm">
          <span class="mb-1 block">{{ t('evaluations.fields.supplierId') }}</span>
          <input v-model.number="form.supplier_id" type="number" class="w-full rounded-xl border px-3 py-2" />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block">{{ t('evaluations.fields.categoryId') }}</span>
          <input
            v-model.number="form.supplier_category_id"
            type="number"
            class="w-full rounded-xl border px-3 py-2"
          />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block">{{ t('evaluations.fields.kind') }}</span>
          <select v-model="form.evaluation_kind" class="w-full rounded-xl border px-3 py-2">
            <option v-for="opt in evaluationKindOptions" :key="opt.value" :value="opt.value">
              {{ t(opt.labelKey) }}
            </option>
          </select>
        </label>
        <label v-if="form.evaluation_kind === 'post_season'" class="block text-sm">
          <span class="mb-1 block">{{ t('evaluations.fields.seasonId') }}</span>
          <input v-model.number="form.season_id" type="number" class="w-full rounded-xl border px-3 py-2" />
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="form.monitoring_recommended" type="checkbox" />
          {{ t('evaluations.fields.monitoringProposed') }}
        </label>
        <p v-if="formError" class="text-sm text-red-700">{{ formError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-xl px-4 py-2 text-sm" @click="createOpen = false">
            {{ t('common.cancel') }}
          </button>
          <button
            type="button"
            class="rounded-xl bg-brand-primary px-4 py-2 text-sm font-bold text-white"
            :disabled="createMutation.isPending.value"
            @click="createDraft"
          >
            {{ t('evaluations.create') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
