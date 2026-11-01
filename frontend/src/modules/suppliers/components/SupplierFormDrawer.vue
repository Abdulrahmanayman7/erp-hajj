<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import { listSupplierCategories } from '../api/categoriesApi'
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
const nameInputRef = ref<HTMLInputElement | null>(null)
let previouslyFocused: HTMLElement | null = null

const isEdit = computed(() => props.editing != null)
const title = computed(() => (isEdit.value ? t('suppliers.editTitle') : t('suppliers.createTitle')))

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

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.open && !props.submitting) {
    event.preventDefault()
    emit('close')
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      await loadCategories()
      previouslyFocused =
        document.activeElement instanceof HTMLElement ? document.activeElement : null
      document.addEventListener('keydown', onKeydown)
      await nextTick()
      nameInputRef.value?.focus()
      return
    }
    document.removeEventListener('keydown', onKeydown)
    await nextTick()
    previouslyFocused?.focus?.()
    previouslyFocused = null
  },
)

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="ctr-drawer">
      <div v-if="open" class="fixed inset-0 z-[70] flex justify-start">
        <button
          type="button"
          class="absolute inset-0 bg-black/40"
          :aria-label="t('suppliers.closeDrawer')"
          @click="emit('close')"
        />
        <aside
          class="relative z-10 flex h-full w-full max-w-lg flex-col bg-brand-surface shadow-2xl"
          role="dialog"
          aria-modal="true"
        >
          <header class="flex items-start justify-between border-b border-brand-border px-5 py-4">
            <div>
              <h2 class="text-lg font-bold text-brand-text">{{ title }}</h2>
              <p class="mt-1 text-sm text-brand-text-secondary">
                {{ isEdit ? t('suppliers.editSubtitle') : t('suppliers.createSubtitle') }}
              </p>
            </div>
            <button type="button" class="rounded-lg p-1 hover:bg-brand-bg" @click="emit('close')">
              <X class="h-5 w-5" />
            </button>
          </header>

          <form class="flex flex-1 flex-col overflow-hidden" @submit.prevent="emit('submit')">
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
              <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">
                {{ formError }}
              </p>

              <div>
                <label class="mb-1 block text-sm font-semibold text-brand-text">{{
                  t('suppliers.fields.displayName')
                }}</label>
                <input
                  ref="nameInputRef"
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
            </div>

            <footer class="flex justify-end gap-2 border-t border-brand-border px-5 py-4">
              <button
                type="button"
                class="rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
                @click="emit('close')"
              >
                {{ t('suppliers.cancel') }}
              </button>
              <button
                type="submit"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                :disabled="submitting"
              >
                <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                {{ isEdit ? t('suppliers.editCta') : t('suppliers.createCta') }}
              </button>
            </footer>
          </form>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.ctr-drawer-enter-active,
.ctr-drawer-leave-active {
  transition: opacity 0.2s ease;
}
.ctr-drawer-enter-from,
.ctr-drawer-leave-to {
  opacity: 0;
}
</style>
