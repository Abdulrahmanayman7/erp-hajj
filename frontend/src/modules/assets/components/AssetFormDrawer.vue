<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import { listWarehouses } from '@/modules/inventory/api/warehousesApi'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { toSelectId, warehouseSelectOption } from '@/shared/lookups/selectOptions'

import type { Asset, AssetFormState } from '../types/assets'
import { ASSET_CONDITIONS } from '../types/assets'

const props = defineProps<{
  open: boolean
  editing: Asset | null
  form: AssetFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  categoryOptions: AppSelectOption[]
  orgUnitOptions: AppSelectOption[]
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [AssetFormState]
}>()

const { t } = useI18n()
const isEdit = computed(() => props.editing != null)

const conditionOptions = computed<AppSelectOption[]>(() =>
  ASSET_CONDITIONS.map((condition) => ({
    value: condition,
    label: t(`assets.condition.${condition}`),
  })),
)

function patch(part: Partial<AssetFormState>): void {
  emit('update:form', { ...props.form, ...part })
}

const fetchActiveWarehouses = (params: { search?: string; page: number; per_page: number }) =>
  listWarehouses({ ...params, is_active: true })
const selectedWarehouse = computed(() =>
  props.editing?.warehouse ? warehouseSelectOption(props.editing.warehouse) : null,
)
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50" role="presentation">
      <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
      <aside
        class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[560px] flex-col bg-brand-surface shadow-xl"
        role="dialog"
        aria-modal="true"
        @click.stop
      >
        <header class="flex shrink-0 items-start justify-between border-b border-brand-border px-4 py-5 sm:px-6">
          <div>
            <h3 class="text-lg font-bold text-brand-text">
              {{ t(isEdit ? 'assets.form.editTitle' : 'assets.form.createTitle') }}
            </h3>
            <p class="mt-1 text-sm text-brand-text-secondary">
              {{ t(isEdit ? 'assets.form.editSubtitle' : 'assets.form.createSubtitle') }}
            </p>
          </div>
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text-muted hover:bg-brand-bg"
            :disabled="submitting"
            :aria-label="t('assets.closeDrawer')"
            @click="emit('close')"
          >
            <X class="h-4 w-4" />
          </button>
        </header>

        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="emit('submit')">
          <div class="flex-1 space-y-5 overflow-y-auto px-4 py-6 sm:px-6">
            <section class="space-y-4">
              <h4 class="text-sm font-bold text-brand-text">{{ t('assets.form.sections.identity') }}</h4>
              <label class="block">
                <span class="text-sm font-medium text-brand-text">{{ t('assets.fields.assetNumber') }}</span>
                <input
                  readonly
                  :value="editing?.asset_number ?? t('assets.numberPlaceholder')"
                  class="mt-1 h-11 w-full rounded-xl border border-brand-border bg-brand-bg px-3 text-sm text-brand-text-muted"
                />
              </label>
              <label class="block">
                <span class="text-sm font-medium text-brand-text">{{ t('assets.fields.name') }}</span>
                <input
                  :value="form.name"
                  required
                  class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
                  @input="patch({ name: ($event.target as HTMLInputElement).value })"
                />
                <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">
                  {{ t(`assets.validation.${fieldErrors.name}`) }}
                </p>
              </label>
              <label class="block">
                <span class="text-sm font-medium text-brand-text">{{ t('assets.fields.description') }}</span>
                <textarea
                  :value="form.description"
                  rows="3"
                  class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
                  @input="patch({ description: ($event.target as HTMLTextAreaElement).value })"
                />
              </label>
              <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                  <span class="text-sm font-medium text-brand-text">{{ t('assets.fields.serialNumber') }}</span>
                  <input
                    :value="form.serial_number"
                    class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
                    @input="patch({ serial_number: ($event.target as HTMLInputElement).value })"
                  />
                </label>
                <label class="block">
                  <span class="text-sm font-medium text-brand-text">{{ t('assets.fields.barcode') }}</span>
                  <input
                    :value="form.barcode"
                    class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
                    @input="patch({ barcode: ($event.target as HTMLInputElement).value })"
                  />
                </label>
              </div>
              <label class="block">
                <span class="text-sm font-medium text-brand-text">{{ t('assets.fields.condition') }}</span>
                <AppSelect
                  class="mt-1"
                  :model-value="form.condition"
                  :options="conditionOptions"
                  @update:model-value="patch({ condition: $event as AssetFormState['condition'] })"
                />
              </label>
            </section>

            <section class="space-y-4">
              <h4 class="text-sm font-bold text-brand-text">{{ t('assets.form.sections.category') }}</h4>
              <label class="block">
                <span class="text-sm font-medium text-brand-text">{{ t('assets.fields.category') }}</span>
                <AppSelect
                  class="mt-1"
                  searchable
                  :model-value="form.category_id"
                  :options="categoryOptions"
                  @update:model-value="patch({ category_id: $event as number | '' })"
                />
              </label>
            </section>

            <section class="space-y-4">
              <h4 class="text-sm font-bold text-brand-text">{{ t('assets.form.sections.location') }}</h4>
              <label class="block">
                <span class="text-sm font-medium text-brand-text">{{ t('assets.fields.warehouse') }}</span>
                <AppRemoteSelect
                  class="mt-1"
                  :model-value="form.warehouse_id"
                  query-key="warehouses-active"
                  :fetcher="fetchActiveWarehouses"
                  :map-option="warehouseSelectOption"
                  :selected-option="selectedWarehouse"
                  :enabled="open"
                  @update:model-value="patch({ warehouse_id: toSelectId($event) })"
                />
              </label>
              <label class="block">
                <span class="text-sm font-medium text-brand-text">{{ t('assets.fields.organizationUnit') }}</span>
                <AppSelect
                  class="mt-1"
                  searchable
                  :model-value="form.organization_unit_id"
                  :options="orgUnitOptions"
                  @update:model-value="patch({ organization_unit_id: $event as number | '' })"
                />
              </label>
            </section>

            <section class="space-y-4">
              <h4 class="text-sm font-bold text-brand-text">{{ t('assets.form.sections.purchase') }}</h4>
              <p class="text-xs text-brand-text-muted">{{ t('assets.form.purchaseHint') }}</p>
              <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                  <span class="text-sm font-medium text-brand-text">{{ t('assets.fields.purchaseValue') }}</span>
                  <input
                    :value="form.purchase_value"
                    type="number"
                    min="0"
                    step="0.01"
                    class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
                    @input="patch({ purchase_value: ($event.target as HTMLInputElement).value })"
                  />
                  <p v-if="fieldErrors.purchase_value" class="mt-1 text-xs text-red-600">
                    {{ t(`assets.validation.${fieldErrors.purchase_value}`) }}
                  </p>
                </label>
                <label class="block">
                  <span class="text-sm font-medium text-brand-text">{{ t('assets.fields.acquisitionDate') }}</span>
                  <input
                    :value="form.acquisition_date"
                    type="date"
                    class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
                    @input="patch({ acquisition_date: ($event.target as HTMLInputElement).value })"
                  />
                </label>
              </div>
            </section>

            <section class="space-y-4">
              <h4 class="text-sm font-bold text-brand-text">{{ t('assets.form.sections.notes') }}</h4>
              <label class="block">
                <span class="text-sm font-medium text-brand-text">{{ t('assets.fields.notes') }}</span>
                <textarea
                  :value="form.notes"
                  rows="3"
                  class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
                  @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })"
                />
              </label>
            </section>

            <p v-if="formError" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ formError }}
            </p>
          </div>

          <footer
            class="flex shrink-0 flex-col-reverse gap-2 border-t border-brand-border px-4 py-3 sm:flex-row sm:justify-end"
            style="padding-bottom: max(12px, env(safe-area-inset-bottom))"
          >
            <button
              type="button"
              class="inline-flex h-11 items-center justify-center rounded-xl border px-4 text-sm font-semibold"
              :disabled="submitting"
              @click="emit('close')"
            >
              {{ t('assets.cancel') }}
            </button>
            <button
              type="submit"
              class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="submitting"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              {{ t('assets.save') }}
            </button>
          </footer>
        </form>
      </aside>
    </div>
  </Teleport>
</template>
