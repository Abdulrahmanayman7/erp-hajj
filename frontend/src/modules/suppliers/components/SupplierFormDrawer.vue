<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { listSupplierCategories } from '../api/categoriesApi'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import type { Supplier, SupplierFormState, SupplierType } from '../types/suppliers'
import { SUPPLIER_TYPES } from '../validation/supplierValidation'

const props = defineProps<{
  open: boolean
  editing: Supplier | null
  form: SupplierFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [SupplierFormState]
}>()

const { t } = useI18n()

const isEdit = computed(() => props.editing != null)
const title = computed(() => (isEdit.value ? t('suppliers.editTitle') : t('suppliers.createTitle')))
const subtitle = computed(() =>
  isEdit.value ? t('suppliers.editSubtitle') : t('suppliers.createSubtitle'),
)
const primaryLabel = computed(() =>
  isEdit.value ? t('suppliers.editCta') : t('suppliers.createCta'),
)

const typeOptions = computed<AppSelectOption[]>(() =>
  SUPPLIER_TYPES.map((type) => ({
    value: type,
    label: t(`suppliers.type.${type}`),
  })),
)

const categoryOptions = ref<AppSelectOption[]>([])

async function loadCategories(): Promise<void> {
  const result = await listSupplierCategories({ is_active: true, per_page: 100 })
  categoryOptions.value = result.data.map((c) => ({
    value: c.id,
    label: c.name,
    hint: c.code ?? undefined,
  }))
}

function patch(partial: Partial<SupplierFormState>): void {
  emit('update:form', { ...props.form, ...partial })
}

function toggleCategory(id: number): void {
  const set = new Set(props.form.category_ids)
  if (set.has(id)) set.delete(id)
  else set.add(id)
  patch({ category_ids: [...set] })
}

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      await loadCategories()
    }
  },
)
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="title"
    :description="subtitle"
    size="md"
    :submitting="submitting"
    :cancel-label="t('suppliers.cancel')"
    :submit-label="primaryLabel"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">
      {{ formError }}
    </p>

    <div>
      <label class="mb-1 block text-sm font-semibold text-brand-text">{{
        t('suppliers.fields.displayName')
      }}</label>
      <input
        :value="form.display_name"
        type="text"
        class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
        @input="patch({ display_name: ($event.target as HTMLInputElement).value })"
      />
      <p v-if="fieldErrors.display_name" class="mt-1 text-xs text-red-600">
        {{ t(`suppliers.validation.${fieldErrors.display_name}`) }}
      </p>
    </div>

    <div>
      <label class="mb-1 block text-sm font-semibold text-brand-text">{{
        t('suppliers.fields.legalName')
      }}</label>
      <input
        :value="form.legal_name"
        type="text"
        class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
        @input="patch({ legal_name: ($event.target as HTMLInputElement).value })"
      />
    </div>

    <div>
      <label class="mb-1 block text-sm font-semibold text-brand-text">{{
        t('suppliers.fields.supplierType')
      }}</label>
      <AppSelect
        :model-value="form.supplier_type"
        :options="typeOptions"
        @update:model-value="patch({ supplier_type: $event as SupplierType })"
      />
    </div>

    <div>
      <label class="mb-1 block text-sm font-semibold text-brand-text">{{
        t('suppliers.fields.countryCode')
      }}</label>
      <input
        :value="form.country_code"
        type="text"
        maxlength="2"
        class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm uppercase"
        @input="
          patch({
            country_code: ($event.target as HTMLInputElement).value.toUpperCase(),
          })
        "
      />
      <p v-if="fieldErrors.country_code" class="mt-1 text-xs text-red-600">
        {{ t(`suppliers.validation.${fieldErrors.country_code}`) }}
      </p>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="mb-1 block text-sm font-semibold text-brand-text">{{
          t('suppliers.fields.legalIdType')
        }}</label>
        <input
          :value="form.legal_id_type"
          type="text"
          class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
          @input="patch({ legal_id_type: ($event.target as HTMLInputElement).value })"
        />
      </div>
      <div>
        <label class="mb-1 block text-sm font-semibold text-brand-text">{{
          t('suppliers.fields.legalIdValue')
        }}</label>
        <input
          :value="form.legal_id_value"
          type="text"
          class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
          @input="patch({ legal_id_value: ($event.target as HTMLInputElement).value })"
        />
      </div>
    </div>

    <div>
      <label class="mb-1 block text-sm font-semibold text-brand-text">{{
        t('suppliers.fields.notes')
      }}</label>
      <textarea
        :value="form.notes"
        rows="3"
        class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
        @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })"
      />
    </div>

    <div>
      <p class="mb-2 text-sm font-semibold text-brand-text">{{ t('suppliers.fields.categories') }}</p>
      <div v-if="categoryOptions.length === 0" class="text-sm text-brand-text-muted">
        {{ t('suppliers.noCategories') }}
      </div>
      <div v-else class="flex flex-wrap gap-2">
        <button
          v-for="opt in categoryOptions"
          :key="String(opt.value)"
          type="button"
          class="rounded-full border px-3 py-1 text-xs font-semibold transition"
          :class="
            form.category_ids.includes(Number(opt.value))
              ? 'border-brand-primary bg-brand-primary/10 text-brand-primary'
              : 'border-brand-border bg-brand-bg text-brand-text-secondary'
          "
          @click="toggleCategory(Number(opt.value))"
        >
          {{ opt.label }}
        </button>
      </div>
    </div>
  </AppFormDrawer>
</template>
