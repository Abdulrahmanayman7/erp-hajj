<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import { listCategories } from '../api/categoriesApi'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import AppDateInput from '@/shared/components/AppDateInput.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, namedCodeOption, toSelectId } from '@/shared/lookups/selectOptions'

import type { Contract, ContractFormState } from '../types/contracts'

const props = defineProps<{
  open: boolean
  editing: Contract | null
  form: ContractFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  orgUnitOptions: AppSelectOption[]
  counterpartyKindOptions: AppSelectOption[]
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [ContractFormState]
}>()

const { t } = useI18n()
const titleInputRef = ref<HTMLInputElement | null>(null)
let previouslyFocused: HTMLElement | null = null

const isEdit = computed(() => props.editing != null)
const title = computed(() => (isEdit.value ? t('contracts.editTitle') : t('contracts.createTitle')))
const subtitle = computed(() =>
  isEdit.value ? t('contracts.editSubtitle') : t('contracts.createSubtitle'),
)
const primaryLabel = computed(() =>
  isEdit.value ? t('contracts.editCta') : t('contracts.createCta'),
)

const contractNumberDisplay = computed(() => {
  if (isEdit.value && props.editing) {
    return props.editing.contract_number
  }
  return t('contracts.contractNumberPlaceholder')
})

function patch(partial: Partial<ContractFormState>): void {
  emit('update:form', { ...props.form, ...partial })
}

const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })
const fetchActiveCategories = (params: { search?: string; page: number; per_page: number }) =>
  listCategories({ ...params, is_active: true })
const emptyEmployee = computed<AppSelectOption>(() => ({ value: '', label: t('contracts.noEmployee') }))
const selectedEmployee = computed(() =>
  props.editing?.employee ? employeeSelectOption(props.editing.employee) : null,
)
const selectedCategory = computed(() =>
  props.editing?.category ? namedCodeOption(props.editing.category) : null,
)

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
      previouslyFocused =
        document.activeElement instanceof HTMLElement ? document.activeElement : null
      document.addEventListener('keydown', onKeydown)
      await nextTick()
      titleInputRef.value?.focus()
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
      <div v-if="open" class="ctr-drawer-root fixed inset-0 z-50" role="presentation">
        <div
          class="ctr-drawer-backdrop absolute inset-0 bg-[rgba(15,23,20,0.32)]"
          aria-hidden="true"
          @click="emit('close')"
        />

        <aside
          class="ctr-drawer-panel absolute inset-y-0 start-0 flex h-dvh w-full max-w-[520px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
          role="dialog"
          aria-modal="true"
          v-autofocus-when
          :aria-label="title"
          @click.stop
        >
          <header
            class="flex shrink-0 items-start justify-between gap-4 border-b border-brand-border px-4 py-5 sm:px-7"
          >
            <div class="min-w-0 text-start">
              <h3 class="text-[21px] font-bold leading-tight text-brand-text">{{ title }}</h3>
              <p class="mt-1.5 text-[13px] leading-relaxed text-brand-text-secondary">
                {{ subtitle }}
              </p>
            </div>
            <button
              type="button"
              class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] text-brand-text-secondary transition hover:bg-brand-bg hover:text-brand-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25"
              :aria-label="t('contracts.closeDrawer')"
              :disabled="submitting"
              @click="emit('close')"
            >
              <X class="h-4 w-4" :stroke-width="2.25" />
            </button>
          </header>

          <form
            id="contract-form-drawer"
            class="flex min-h-0 flex-1 flex-col"
            @submit.prevent="emit('submit')"
          >
            <div class="flex-1 space-y-6 overflow-y-auto px-4 py-6 sm:px-7">
              <section class="space-y-4">
                <h4 class="text-sm font-bold text-brand-text">
                  {{ t('contracts.sections.basics') }}
                </h4>

                <label class="block">
                  <span class="mb-2 block text-sm font-semibold text-brand-text">
                    {{ t('contracts.fields.contractNumber') }}
                  </span>
                  <input
                    :value="contractNumberDisplay"
                    type="text"
                    readonly
                    class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-bg px-3.5 text-sm text-brand-text-secondary outline-none"
                  />
                  <p class="mt-1.5 text-xs text-brand-text-muted">
                    {{ t('contracts.contractNumberHint') }}
                  </p>
                </label>

                <label class="block">
                  <span class="mb-2 block text-sm font-semibold text-brand-text">
                    {{ t('contracts.fields.title') }}
                  </span>
                  <input
                    ref="titleInputRef"
                    :value="form.title"
                    type="text"
                    required
                    class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
                    :disabled="submitting"
                    @input="patch({ title: ($event.target as HTMLInputElement).value })"
                  />
                  <p v-if="fieldErrors.title" class="mt-1.5 text-xs text-red-600">
                    {{ fieldErrors.title }}
                  </p>
                </label>

                <div>
                  <span class="mb-2 block text-sm font-semibold text-brand-text">
                    {{ t('contracts.fields.category') }}
                  </span>
                  <AppRemoteSelect
                    :model-value="form.contract_category_id"
                    query-key="contract-categories-active"
                    :fetcher="fetchActiveCategories"
                    :map-option="namedCodeOption"
                    :selected-option="selectedCategory"
                    :placeholder="t('contracts.selectCategory')"
                    :disabled="submitting"
                    :enabled="open"
                    @update:model-value="patch({ contract_category_id: toSelectId($event) })"
                  />
                  <p v-if="fieldErrors.contract_category_id" class="mt-1.5 text-xs text-red-600">
                    {{ fieldErrors.contract_category_id }}
                  </p>
                </div>
              </section>

              <section class="space-y-4">
                <h4 class="text-sm font-bold text-brand-text">
                  {{ t('contracts.sections.counterparty') }}
                </h4>

                <label class="block">
                  <span class="mb-2 block text-sm font-semibold text-brand-text">
                    {{ t('contracts.fields.counterpartyName') }}
                  </span>
                  <input
                    :value="form.counterparty_name"
                    type="text"
                    required
                    class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
                    :disabled="submitting"
                    @input="patch({ counterparty_name: ($event.target as HTMLInputElement).value })"
                  />
                  <p v-if="fieldErrors.counterparty_name" class="mt-1.5 text-xs text-red-600">
                    {{ fieldErrors.counterparty_name }}
                  </p>
                </label>

                <div>
                  <span class="mb-2 block text-sm font-semibold text-brand-text">
                    {{ t('contracts.fields.counterpartyKind') }}
                  </span>
                  <AppSelect
                    :model-value="form.counterparty_kind"
                    :options="counterpartyKindOptions"
                    :disabled="submitting"
                    @update:model-value="
                      patch({
                        counterparty_kind:
                          ($event as ContractFormState['counterparty_kind']) || 'organization',
                      })
                    "
                  />
                </div>

                <div>
                  <span class="mb-2 block text-sm font-semibold text-brand-text">
                    {{ t('contracts.fields.employee') }}
                  </span>
                  <AppRemoteSelect
                    :model-value="form.employee_id"
                    query-key="employees-active"
                    :fetcher="fetchActiveEmployees"
                    :map-option="employeeSelectOption"
                    :empty-option="emptyEmployee"
                    :selected-option="selectedEmployee"
                    :placeholder="t('contracts.selectEmployee')"
                    :disabled="submitting"
                    :enabled="open"
                    @update:model-value="patch({ employee_id: toSelectId($event) })"
                  />
                </div>
              </section>

              <section class="space-y-4">
                <h4 class="text-sm font-bold text-brand-text">
                  {{ t('contracts.sections.organization') }}
                </h4>
                <div>
                  <span class="mb-2 block text-sm font-semibold text-brand-text">
                    {{ t('contracts.fields.organizationUnit') }}
                  </span>
                  <AppSelect
                    :model-value="form.organization_unit_id"
                    :options="orgUnitOptions"
                    :placeholder="t('contracts.selectOrgUnit')"
                    :disabled="submitting"
                    searchable
                    @update:model-value="
                      patch({
                        organization_unit_id:
                          $event === null || $event === '' ? '' : Number($event),
                      })
                    "
                  />
                </div>
              </section>

              <section class="space-y-4">
                <h4 class="text-sm font-bold text-brand-text">
                  {{ t('contracts.sections.duration') }}
                </h4>
                <div class="grid gap-4 sm:grid-cols-2">
                  <label class="block">
                    <span class="mb-2 block text-sm font-semibold text-brand-text">
                      {{ t('contracts.fields.startDate') }}
                    </span>
                    <AppDateInput
                      :model-value="form.start_date"
                      :disabled="submitting"
                      :invalid="Boolean(fieldErrors.start_date)"
                      @update:model-value="patch({ start_date: $event })"
                    />
                    <p v-if="fieldErrors.start_date" class="mt-1.5 text-xs text-red-600">
                      {{ fieldErrors.start_date }}
                    </p>
                  </label>
                  <label class="block">
                    <span class="mb-2 block text-sm font-semibold text-brand-text">
                      {{ t('contracts.fields.endDate') }}
                    </span>
                    <AppDateInput
                      :model-value="form.end_date"
                      :disabled="submitting"
                      :invalid="Boolean(fieldErrors.end_date)"
                      @update:model-value="patch({ end_date: $event })"
                    />
                    <p v-if="fieldErrors.end_date" class="mt-1.5 text-xs text-red-600">
                      {{ fieldErrors.end_date }}
                    </p>
                  </label>
                </div>
              </section>

              <section class="space-y-4">
                <h4 class="text-sm font-bold text-brand-text">
                  {{ t('contracts.sections.value') }}
                </h4>
                <div class="grid gap-4 sm:grid-cols-2">
                  <label class="block">
                    <span class="mb-2 block text-sm font-semibold text-brand-text">
                      {{ t('contracts.fields.value') }}
                    </span>
                    <input
                      :value="form.value"
                      type="text"
                      inputmode="decimal"
                      dir="ltr"
                      class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
                      :disabled="submitting"
                      @input="patch({ value: ($event.target as HTMLInputElement).value })"
                    />
                    <p v-if="fieldErrors.value" class="mt-1.5 text-xs text-red-600">
                      {{ fieldErrors.value }}
                    </p>
                  </label>
                  <label class="block">
                    <span class="mb-2 block text-sm font-semibold text-brand-text">
                      {{ t('contracts.fields.currency') }}
                    </span>
                    <input
                      :value="form.currency"
                      type="text"
                      readonly
                      class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-bg px-3.5 text-sm text-brand-text-secondary outline-none"
                    />
                  </label>
                </div>
              </section>

              <section class="space-y-4">
                <h4 class="text-sm font-bold text-brand-text">
                  {{ t('contracts.sections.notes') }}
                </h4>
                <label class="block">
                  <span class="mb-2 block text-sm font-semibold text-brand-text">
                    {{ t('contracts.fields.notes') }}
                  </span>
                  <textarea
                    :value="form.notes"
                    rows="3"
                    class="w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 py-3 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
                    :disabled="submitting"
                    @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })"
                  />
                </label>
              </section>

              <p
                v-if="formError"
                class="rounded-[11px] border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-700"
                role="alert"
              >
                {{ formError }}
              </p>
            </div>

            <footer
              class="flex shrink-0 flex-col-reverse gap-2 border-t border-brand-border bg-brand-surface px-4 py-3 sm:flex-row sm:justify-end"
              style="padding-bottom: max(12px, env(safe-area-inset-bottom))"
            >
              <button
                type="button"
                class="inline-flex h-11 items-center justify-center rounded-[10px] border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-text transition hover:bg-brand-bg disabled:opacity-50"
                :disabled="submitting"
                @click="emit('close')"
              >
                {{ t('contracts.cancel') }}
              </button>
              <button
                type="submit"
                class="inline-flex h-11 min-w-[8.5rem] items-center justify-center gap-2 rounded-[10px] bg-brand-primary-dark px-5 text-sm font-semibold text-white transition hover:bg-brand-primary disabled:opacity-60"
                :disabled="submitting"
              >
                <Loader2
                  v-if="submitting"
                  class="h-4 w-4 animate-spin"
                  :stroke-width="2.25"
                  aria-hidden="true"
                />
                <span>{{ submitting ? t('contracts.saving') : primaryLabel }}</span>
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
  pointer-events: none;
}

.ctr-drawer-enter-active .ctr-drawer-backdrop {
  transition: opacity 200ms ease;
}

.ctr-drawer-leave-active .ctr-drawer-backdrop {
  transition: opacity 180ms ease;
}

.ctr-drawer-enter-from .ctr-drawer-backdrop,
.ctr-drawer-leave-to .ctr-drawer-backdrop {
  opacity: 0;
}

.ctr-drawer-enter-active .ctr-drawer-panel {
  transition:
    transform 280ms cubic-bezier(0.22, 1, 0.36, 1),
    opacity 280ms cubic-bezier(0.22, 1, 0.36, 1);
}

.ctr-drawer-leave-active .ctr-drawer-panel {
  transition:
    transform 220ms cubic-bezier(0.22, 1, 0.36, 1),
    opacity 220ms cubic-bezier(0.22, 1, 0.36, 1);
}

.ctr-drawer-enter-from .ctr-drawer-panel,
.ctr-drawer-leave-to .ctr-drawer-panel {
  transform: translateX(100%);
  opacity: 0.92;
}

.ctr-drawer-enter-to .ctr-drawer-panel,
.ctr-drawer-leave-from .ctr-drawer-panel {
  transform: translateX(0);
  opacity: 1;
}

[dir='ltr'] .ctr-drawer-enter-from .ctr-drawer-panel,
[dir='ltr'] .ctr-drawer-leave-to .ctr-drawer-panel {
  transform: translateX(-100%);
}

@media (prefers-reduced-motion: reduce) {
  .ctr-drawer-enter-active .ctr-drawer-backdrop,
  .ctr-drawer-leave-active .ctr-drawer-backdrop,
  .ctr-drawer-enter-active .ctr-drawer-panel,
  .ctr-drawer-leave-active .ctr-drawer-panel {
    transition: none;
  }

  .ctr-drawer-enter-from .ctr-drawer-panel,
  .ctr-drawer-leave-to .ctr-drawer-panel,
  [dir='ltr'] .ctr-drawer-enter-from .ctr-drawer-panel,
  [dir='ltr'] .ctr-drawer-leave-to .ctr-drawer-panel {
    transform: none;
    opacity: 1;
  }
}
</style>
