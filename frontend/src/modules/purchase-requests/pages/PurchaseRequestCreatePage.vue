<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, RouterLink } from 'vue-router'
import { ArrowRight, Loader2, Plus, Trash2 } from 'lucide-vue-next'

import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import { listSuppliers } from '@/modules/suppliers/api/suppliersApi'
import type { Supplier } from '@/modules/suppliers/types/suppliers'
import { ApiError } from '@/shared/api/http'
import AppDateInput from '@/shared/components/AppDateInput.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useToast } from '@/shared/composables/useToast'
import { toSelectNullableId } from '@/shared/lookups/selectOptions'

import { useCreatePurchaseRequestMutation } from '../mutations/usePurchaseRequestMutations'
import type { PurchaseRequestKind, PurchaseRequestPriority } from '../types/purchaseRequests'
import {
  mapPurchaseRequestErrorCode,
  priorityOptions,
  requestKindOptions,
  validateCreatePurchaseRequest,
} from '../validation/purchaseRequestValidation'

const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const createMutation = useCreatePurchaseRequestMutation()
const { data: unitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })
const { data: seasonsData } = useSeasonsQuery({ per_page: 100 })

const formError = ref('')
const form = reactive({
  request_kind: 'administrative' as PurchaseRequestKind,
  season_id: null as number | null,
  title: '',
  purpose: '',
  priority: 'normal' as PurchaseRequestPriority,
  urgency_justification: '',
  beneficiary_organization_unit_id: '' as number | '',
  optional_supplier_id: '' as number | '',
  currency: 'SAR',
  lines: [
    {
      description: '',
      specifications: '',
      quantity: 1,
      unit: 'قطعة',
      need_date: '',
      unit_estimate: null as number | null,
      unpriced_reason: '',
    },
  ],
})

const kindSelectOptions = computed<AppSelectOption[]>(() =>
  requestKindOptions.map((opt) => ({ value: opt.value, label: t(opt.labelKey) })),
)
const prioritySelectOptions = computed<AppSelectOption[]>(() =>
  priorityOptions.map((opt) => ({ value: opt.value, label: t(opt.labelKey) })),
)
const unitOptions = computed<AppSelectOption[]>(() =>
  (unitsData.value?.data ?? []).map((unit) => ({
    value: unit.id,
    label: unit.name,
    hint: unit.code,
  })),
)
const seasonOptions = computed<AppSelectOption[]>(() =>
  (seasonsData.value?.data ?? []).map((season) => ({
    value: season.id,
    label: `${season.name} (${season.status})`,
  })),
)

const fetchSuppliers = (params: { search?: string; page: number; per_page: number }) =>
  listSuppliers({ q: params.search, page: params.page, per_page: params.per_page })
const mapSupplier = (row: Supplier): AppSelectOption => ({
  value: row.id,
  label: row.display_name,
  hint: row.supplier_number,
})

function addLine(): void {
  form.lines.push({
    description: '',
    specifications: '',
    quantity: 1,
    unit: 'قطعة',
    need_date: '',
    unit_estimate: null,
    unpriced_reason: '',
  })
}

function removeLine(index: number): void {
  if (form.lines.length <= 1) return
  form.lines.splice(index, 1)
}

async function submit(): Promise<void> {
  formError.value = ''
  const payload = {
    request_kind: form.request_kind,
    season_id: form.request_kind === 'seasonal' ? form.season_id : null,
    title: form.title.trim(),
    purpose: form.purpose.trim(),
    priority: form.priority,
    urgency_justification: form.urgency_justification.trim() || null,
    beneficiary_organization_unit_id: Number(form.beneficiary_organization_unit_id) || 0,
    optional_supplier_id: toSelectNullableId(form.optional_supplier_id),
    currency: form.currency,
    lines: form.lines.map((line, index) => ({
      line_no: index + 1,
      description: line.description.trim(),
      specifications: line.specifications.trim() || null,
      quantity: Number(line.quantity),
      unit: line.unit.trim(),
      need_date: line.need_date,
      unit_estimate:
        line.unit_estimate != null && line.unit_estimate !== ('' as unknown)
          ? Number(line.unit_estimate)
          : null,
      unpriced_reason: line.unpriced_reason.trim() || null,
    })),
  }

  const errors = validateCreatePurchaseRequest(payload)
  if (Object.keys(errors).length) {
    formError.value = t('purchaseRequests.errors.validation')
    return
  }

  try {
    const created = await createMutation.mutateAsync(payload)
    toast.success(t('purchaseRequests.created'))
    await router.push(`/app/purchase-requests/${created.id}`)
  } catch (error) {
    const code = error instanceof ApiError ? error.code : undefined
    formError.value = t(mapPurchaseRequestErrorCode(code))
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader :title="t('purchaseRequests.createTitle')" :subtitle="t('purchaseRequests.createSubtitle')">
      <template #actions>
        <RouterLink
          to="/app/purchase-requests"
          class="inline-flex h-11 items-center gap-2 rounded-xl border border-brand-border px-4 text-sm font-semibold text-brand-text transition hover:bg-brand-bg"
        >
          <ArrowRight class="h-4 w-4" />
          {{ t('purchaseRequests.backToList') }}
        </RouterLink>
      </template>
    </AppPageHeader>

    <form
      class="space-y-6 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)] sm:p-6"
      @submit.prevent="submit"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block space-y-1.5 text-sm sm:col-span-1">
          <span class="font-semibold text-brand-text">{{ t('purchaseRequests.fields.kind') }}</span>
          <AppSelect
            :model-value="form.request_kind"
            :options="kindSelectOptions"
            @update:model-value="
              form.request_kind = ($event as PurchaseRequestKind) || 'administrative'
            "
          />
        </label>

        <label v-if="form.request_kind === 'seasonal'" class="block space-y-1.5 text-sm">
          <span class="font-semibold text-brand-text">{{ t('purchaseRequests.fields.seasonId') }}</span>
          <AppSelect
            :model-value="form.season_id"
            :options="seasonOptions"
            searchable
            @update:model-value="form.season_id = ($event as number) ?? null"
          />
        </label>

        <label class="block space-y-1.5 text-sm sm:col-span-2">
          <span class="font-semibold text-brand-text">{{ t('purchaseRequests.fields.title') }}</span>
          <input
            v-model="form.title"
            type="text"
            class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/30"
          />
        </label>

        <label class="block space-y-1.5 text-sm sm:col-span-2">
          <span class="font-semibold text-brand-text">{{ t('purchaseRequests.fields.purpose') }}</span>
          <textarea
            v-model="form.purpose"
            rows="3"
            class="w-full rounded-xl border border-brand-border bg-brand-surface px-3 py-2.5 text-sm text-brand-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/30"
          />
        </label>

        <label class="block space-y-1.5 text-sm">
          <span class="font-semibold text-brand-text">{{ t('purchaseRequests.fields.priority') }}</span>
          <AppSelect
            :model-value="form.priority"
            :options="prioritySelectOptions"
            @update:model-value="form.priority = ($event as PurchaseRequestPriority) || 'normal'"
          />
        </label>

        <label class="block space-y-1.5 text-sm">
          <span class="font-semibold text-brand-text">{{ t('purchaseRequests.fields.unit') }}</span>
          <AppSelect
            :model-value="form.beneficiary_organization_unit_id"
            :options="unitOptions"
            searchable
            :placeholder="t('purchaseRequests.fields.unitPlaceholder')"
            @update:model-value="
              form.beneficiary_organization_unit_id = $event === null || $event === '' ? '' : Number($event)
            "
          />
        </label>

        <label
          v-if="form.priority !== 'normal'"
          class="block space-y-1.5 text-sm sm:col-span-2"
        >
          <span class="font-semibold text-brand-text">{{ t('purchaseRequests.fields.urgency') }}</span>
          <textarea
            v-model="form.urgency_justification"
            rows="2"
            class="w-full rounded-xl border border-brand-border bg-brand-surface px-3 py-2.5 text-sm text-brand-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/30"
          />
        </label>

        <label class="block space-y-1.5 text-sm sm:col-span-2">
          <span class="font-semibold text-brand-text">{{
            t('purchaseRequests.fields.optionalSupplierId')
          }}</span>
          <AppRemoteSelect
            v-model="form.optional_supplier_id"
            query-key="purchase-request-suppliers"
            :fetcher="fetchSuppliers"
            :map-option="mapSupplier"
            clearable
            searchable
            :placeholder="t('purchaseRequests.fields.optional')"
          />
        </label>
      </div>

      <section class="space-y-4 border-t border-brand-border pt-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-base font-bold text-brand-text">{{ t('purchaseRequests.linesTitle') }}</h2>
          <button
            type="button"
            class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border px-3 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-bg"
            @click="addLine"
          >
            <Plus class="h-4 w-4" />
            {{ t('purchaseRequests.addLine') }}
          </button>
        </div>

        <div
          v-for="(line, index) in form.lines"
          :key="index"
          class="space-y-3 rounded-2xl border border-brand-border bg-brand-bg/40 p-4"
        >
          <div class="flex items-center justify-between gap-3">
            <span class="text-sm font-semibold text-brand-text">{{
              t('purchaseRequests.lineN', { n: index + 1 })
            }}</span>
            <button
              v-if="form.lines.length > 1"
              type="button"
              class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-red-700 transition hover:bg-red-50"
              @click="removeLine(index)"
            >
              <Trash2 class="h-4 w-4" />
              {{ t('purchaseRequests.removeLine') }}
            </button>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block space-y-1.5 text-sm sm:col-span-2">
              <span class="font-medium text-brand-text">{{ t('purchaseRequests.fields.description') }}</span>
              <input
                v-model="line.description"
                type="text"
                class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/30"
              />
            </label>
            <label class="block space-y-1.5 text-sm">
              <span class="font-medium text-brand-text">{{ t('purchaseRequests.fields.quantity') }}</span>
              <input
                v-model.number="line.quantity"
                type="number"
                min="0.0001"
                step="any"
                class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/30"
              />
            </label>
            <label class="block space-y-1.5 text-sm">
              <span class="font-medium text-brand-text">{{ t('purchaseRequests.fields.unitMeasure') }}</span>
              <input
                v-model="line.unit"
                type="text"
                class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/30"
              />
            </label>
            <label class="block space-y-1.5 text-sm">
              <span class="font-medium text-brand-text">{{ t('purchaseRequests.fields.needDate') }}</span>
              <AppDateInput v-model="line.need_date" />
            </label>
            <label class="block space-y-1.5 text-sm">
              <span class="font-medium text-brand-text">{{ t('purchaseRequests.fields.unitEstimate') }}</span>
              <input
                v-model.number="line.unit_estimate"
                type="number"
                min="0"
                step="0.01"
                class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/30"
              />
            </label>
            <label class="block space-y-1.5 text-sm sm:col-span-2">
              <span class="font-medium text-brand-text">{{
                t('purchaseRequests.fields.unpricedReason')
              }}</span>
              <input
                v-model="line.unpriced_reason"
                type="text"
                class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/30"
              />
            </label>
          </div>
        </div>
      </section>

      <p
        v-if="formError"
        class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
      >
        {{ formError }}
      </p>

      <div
        class="flex flex-col-reverse gap-2 border-t border-brand-border pt-4 sm:flex-row sm:justify-end"
      >
        <RouterLink
          to="/app/purchase-requests"
          class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold text-brand-text"
        >
          {{ t('purchaseRequests.discard') }}
        </RouterLink>
        <button
          type="submit"
          class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50 sm:min-w-[9rem]"
          :disabled="createMutation.isPending.value"
        >
          <Loader2 v-if="createMutation.isPending.value" class="h-4 w-4 animate-spin" />
          {{ t('purchaseRequests.saveDraft') }}
        </button>
      </div>
    </form>
  </div>
</template>
