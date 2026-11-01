<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'

import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { ApiError } from '@/shared/api/http'
import { useToast } from '@/shared/composables/useToast'

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
const { data: unitsData } = useOrganizationUnitsFlatQuery({})
const units = computed(() => unitsData.value?.data ?? [])

const formError = ref('')
const form = reactive({
  request_kind: 'administrative' as PurchaseRequestKind,
  season_id: null as number | null,
  title: '',
  purpose: '',
  priority: 'normal' as PurchaseRequestPriority,
  urgency_justification: '',
  beneficiary_organization_unit_id: 0,
  optional_supplier_id: null as number | null,
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
    beneficiary_organization_unit_id: Number(form.beneficiary_organization_unit_id),
    optional_supplier_id: form.optional_supplier_id ? Number(form.optional_supplier_id) : null,
    currency: form.currency,
    lines: form.lines.map((line, index) => ({
      line_no: index + 1,
      description: line.description.trim(),
      specifications: line.specifications.trim() || null,
      quantity: Number(line.quantity),
      unit: line.unit.trim(),
      need_date: line.need_date,
      unit_estimate: line.unit_estimate != null && line.unit_estimate !== ('' as unknown)
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
  <div class="mx-auto max-w-4xl space-y-6">
    <RouterLink
      to="/app/purchase-requests"
      class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
    >
      <ArrowRight class="h-4 w-4" />
      {{ t('purchaseRequests.backToList') }}
    </RouterLink>

    <header>
      <h1 class="text-2xl font-semibold">{{ t('purchaseRequests.createTitle') }}</h1>
      <p class="mt-1 text-sm text-muted-foreground">{{ t('purchaseRequests.createSubtitle') }}</p>
    </header>

    <form class="space-y-5" @submit.prevent="submit">
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block space-y-1 text-sm">
          <span>{{ t('purchaseRequests.fields.kind') }}</span>
          <select v-model="form.request_kind" class="w-full rounded-md border border-border bg-background px-3 py-2">
            <option v-for="opt in requestKindOptions" :key="opt.value" :value="opt.value">
              {{ t(opt.labelKey) }}
            </option>
          </select>
        </label>
        <label v-if="form.request_kind === 'seasonal'" class="block space-y-1 text-sm">
          <span>{{ t('purchaseRequests.fields.seasonId') }}</span>
          <input
            v-model.number="form.season_id"
            type="number"
            min="1"
            class="w-full rounded-md border border-border bg-background px-3 py-2"
          />
        </label>
        <label class="block space-y-1 text-sm sm:col-span-2">
          <span>{{ t('purchaseRequests.fields.title') }}</span>
          <input v-model="form.title" type="text" class="w-full rounded-md border border-border bg-background px-3 py-2" />
        </label>
        <label class="block space-y-1 text-sm sm:col-span-2">
          <span>{{ t('purchaseRequests.fields.purpose') }}</span>
          <textarea v-model="form.purpose" rows="3" class="w-full rounded-md border border-border bg-background px-3 py-2" />
        </label>
        <label class="block space-y-1 text-sm">
          <span>{{ t('purchaseRequests.fields.priority') }}</span>
          <select v-model="form.priority" class="w-full rounded-md border border-border bg-background px-3 py-2">
            <option v-for="opt in priorityOptions" :key="opt.value" :value="opt.value">
              {{ t(opt.labelKey) }}
            </option>
          </select>
        </label>
        <label class="block space-y-1 text-sm">
          <span>{{ t('purchaseRequests.fields.unit') }}</span>
          <select
            v-model.number="form.beneficiary_organization_unit_id"
            class="w-full rounded-md border border-border bg-background px-3 py-2"
          >
            <option :value="0">{{ t('purchaseRequests.fields.unitPlaceholder') }}</option>
            <option v-for="unit in units" :key="unit.id" :value="unit.id">
              {{ unit.name }}
            </option>
          </select>
        </label>
        <label
          v-if="form.priority !== 'normal'"
          class="block space-y-1 text-sm sm:col-span-2"
        >
          <span>{{ t('purchaseRequests.fields.urgency') }}</span>
          <textarea
            v-model="form.urgency_justification"
            rows="2"
            class="w-full rounded-md border border-border bg-background px-3 py-2"
          />
        </label>
        <label class="block space-y-1 text-sm">
          <span>{{ t('purchaseRequests.fields.optionalSupplierId') }}</span>
          <input
            v-model.number="form.optional_supplier_id"
            type="number"
            min="1"
            class="w-full rounded-md border border-border bg-background px-3 py-2"
            :placeholder="t('purchaseRequests.fields.optional')"
          />
        </label>
      </div>

      <section class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-medium">{{ t('purchaseRequests.linesTitle') }}</h2>
          <button type="button" class="text-sm text-primary" @click="addLine">
            {{ t('purchaseRequests.addLine') }}
          </button>
        </div>
        <div
          v-for="(line, index) in form.lines"
          :key="index"
          class="space-y-3 rounded-md border border-border p-4"
        >
          <div class="flex items-center justify-between text-sm font-medium">
            <span>{{ t('purchaseRequests.lineN', { n: index + 1 }) }}</span>
            <button
              v-if="form.lines.length > 1"
              type="button"
              class="text-destructive"
              @click="removeLine(index)"
            >
              {{ t('purchaseRequests.removeLine') }}
            </button>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block space-y-1 text-sm sm:col-span-2">
              <span>{{ t('purchaseRequests.fields.description') }}</span>
              <input v-model="line.description" type="text" class="w-full rounded-md border border-border bg-background px-3 py-2" />
            </label>
            <label class="block space-y-1 text-sm">
              <span>{{ t('purchaseRequests.fields.quantity') }}</span>
              <input v-model.number="line.quantity" type="number" min="0.0001" step="any" class="w-full rounded-md border border-border bg-background px-3 py-2" />
            </label>
            <label class="block space-y-1 text-sm">
              <span>{{ t('purchaseRequests.fields.unitMeasure') }}</span>
              <input v-model="line.unit" type="text" class="w-full rounded-md border border-border bg-background px-3 py-2" />
            </label>
            <label class="block space-y-1 text-sm">
              <span>{{ t('purchaseRequests.fields.needDate') }}</span>
              <input v-model="line.need_date" type="date" class="w-full rounded-md border border-border bg-background px-3 py-2" />
            </label>
            <label class="block space-y-1 text-sm">
              <span>{{ t('purchaseRequests.fields.unitEstimate') }}</span>
              <input v-model.number="line.unit_estimate" type="number" min="0" step="0.01" class="w-full rounded-md border border-border bg-background px-3 py-2" />
            </label>
            <label class="block space-y-1 text-sm sm:col-span-2">
              <span>{{ t('purchaseRequests.fields.unpricedReason') }}</span>
              <input v-model="line.unpriced_reason" type="text" class="w-full rounded-md border border-border bg-background px-3 py-2" />
            </label>
          </div>
        </div>
      </section>

      <p v-if="formError" class="text-sm text-destructive">{{ formError }}</p>

      <div class="flex gap-3">
        <button
          type="submit"
          class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
          :disabled="createMutation.isPending.value"
        >
          {{ t('purchaseRequests.saveDraft') }}
        </button>
        <RouterLink
          to="/app/purchase-requests"
          class="rounded-md border border-border px-4 py-2 text-sm"
        >
          {{ t('purchaseRequests.discard') }}
        </RouterLink>
      </div>
    </form>
  </div>
</template>
