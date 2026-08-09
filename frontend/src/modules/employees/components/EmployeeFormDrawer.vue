<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import type { Employee, EmployeeFormState } from '../types/employees'

const props = defineProps<{
  open: boolean
  editing: Employee | null
  form: EmployeeFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  orgUnitOptions: AppSelectOption[]
  positionOptions: AppSelectOption[]
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [EmployeeFormState]
}>()

const { t } = useI18n()
const nameInputRef = ref<HTMLInputElement | null>(null)
let previouslyFocused: HTMLElement | null = null

const isEdit = computed(() => props.editing != null)
const title = computed(() => (isEdit.value ? t('employees.editTitle') : t('employees.createTitle')))
const subtitle = computed(() =>
  isEdit.value ? t('employees.editSubtitle') : t('employees.createSubtitle'),
)
const primaryLabel = computed(() =>
  isEdit.value ? t('employees.editCta') : t('employees.createCta'),
)

const employeeNumberDisplay = computed(() => {
  if (isEdit.value && props.editing) {
    return props.editing.employee_number
  }
  return t('employees.employeeNumberPlaceholder')
})

function patch(partial: Partial<EmployeeFormState>): void {
  emit('update:form', { ...props.form, ...partial })
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
    <Transition name="emp-drawer">
      <div v-if="open" class="emp-drawer-root fixed inset-0 z-50" role="presentation">
        <div
          class="emp-drawer-backdrop absolute inset-0 bg-[rgba(15,23,20,0.32)]"
          aria-hidden="true"
          @click="emit('close')"
        />

        <aside
          class="emp-drawer-panel absolute inset-y-0 start-0 flex h-dvh w-full max-w-[480px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
          role="dialog"
          aria-modal="true"
          :aria-label="title"
          @click.stop
        >
          <header
            class="flex shrink-0 items-start justify-between gap-4 border-b border-brand-border px-6 py-5 sm:px-7"
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
              :aria-label="t('employees.closeDrawer')"
              :disabled="submitting"
              @click="emit('close')"
            >
              <X class="h-4 w-4" :stroke-width="2.25" />
            </button>
          </header>

          <form
            id="employee-form-drawer"
            class="flex min-h-0 flex-1 flex-col"
            @submit.prevent="emit('submit')"
          >
            <div class="flex-1 space-y-5 overflow-y-auto px-6 py-6 sm:px-7">
              <label class="block">
                <span class="mb-2 block text-sm font-semibold text-brand-text">
                  {{ t('employees.fields.fullName') }}
                </span>
                <input
                  ref="nameInputRef"
                  :value="form.full_name"
                  type="text"
                  required
                  autocomplete="name"
                  class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
                  :disabled="submitting"
                  @input="patch({ full_name: ($event.target as HTMLInputElement).value })"
                />
                <p v-if="fieldErrors.full_name" class="mt-1.5 text-xs text-red-600">
                  {{ fieldErrors.full_name }}
                </p>
              </label>

              <label class="block">
                <span class="mb-2 block text-sm font-semibold text-brand-text">
                  {{ t('employees.fields.employeeNumber') }}
                </span>
                <input
                  :value="employeeNumberDisplay"
                  type="text"
                  readonly
                  class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-bg px-3.5 text-sm text-brand-text-secondary outline-none"
                  :aria-describedby="'employee-number-hint'"
                />
                <p id="employee-number-hint" class="mt-1.5 text-xs text-brand-text-muted">
                  {{ t('employees.employeeNumberHint') }}
                </p>
              </label>

              <div>
                <span class="mb-2 block text-sm font-semibold text-brand-text">
                  {{ t('employees.fields.organizationUnit') }}
                </span>
                <AppSelect
                  :model-value="form.organization_unit_id"
                  :options="orgUnitOptions"
                  :placeholder="t('employees.selectOrgUnit')"
                  :disabled="submitting"
                  searchable
                  @update:model-value="
                    patch({
                      organization_unit_id:
                        $event === null || $event === '' ? '' : Number($event),
                    })
                  "
                />
                <p v-if="fieldErrors.organization_unit_id" class="mt-1.5 text-xs text-red-600">
                  {{ fieldErrors.organization_unit_id }}
                </p>
              </div>

              <div>
                <span class="mb-2 block text-sm font-semibold text-brand-text">
                  {{ t('employees.fields.position') }}
                </span>
                <AppSelect
                  :model-value="form.position_id"
                  :options="positionOptions"
                  :placeholder="t('employees.selectPosition')"
                  :disabled="submitting"
                  searchable
                  @update:model-value="
                    patch({
                      position_id: $event === null || $event === '' ? '' : Number($event),
                    })
                  "
                />
              </div>

              <label class="block">
                <span class="mb-2 block text-sm font-semibold text-brand-text">
                  {{ t('employees.fields.phone') }}
                </span>
                <input
                  :value="form.phone"
                  type="tel"
                  dir="ltr"
                  class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
                  :disabled="submitting"
                  @input="patch({ phone: ($event.target as HTMLInputElement).value })"
                />
              </label>

              <label class="block">
                <span class="mb-2 block text-sm font-semibold text-brand-text">
                  {{ t('employees.fields.email') }}
                </span>
                <input
                  :value="form.email"
                  type="email"
                  dir="ltr"
                  class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
                  :disabled="submitting"
                  @input="patch({ email: ($event.target as HTMLInputElement).value })"
                />
                <p v-if="fieldErrors.email" class="mt-1.5 text-xs text-red-600">
                  {{ fieldErrors.email }}
                </p>
              </label>

              <label class="block">
                <span class="mb-2 block text-sm font-semibold text-brand-text">
                  {{ t('employees.fields.hireDate') }}
                </span>
                <input
                  :value="form.hire_date"
                  type="date"
                  class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
                  :disabled="submitting"
                  @input="patch({ hire_date: ($event.target as HTMLInputElement).value })"
                />
              </label>

              <label class="block">
                <span class="mb-2 block text-sm font-semibold text-brand-text">
                  {{ t('employees.fields.notes') }}
                </span>
                <textarea
                  :value="form.notes"
                  rows="3"
                  class="w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 py-3 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
                  :disabled="submitting"
                  @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })"
                />
              </label>

              <p
                v-if="formError"
                class="rounded-[11px] border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-700"
                role="alert"
              >
                {{ formError }}
              </p>
            </div>

            <footer
              class="flex shrink-0 items-center justify-end gap-2.5 border-t border-brand-border bg-brand-surface px-6 py-4 sm:px-7"
            >
              <button
                type="button"
                class="inline-flex h-11 items-center justify-center rounded-[10px] border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-text transition hover:bg-brand-bg disabled:opacity-50"
                :disabled="submitting"
                @click="emit('close')"
              >
                {{ t('employees.cancel') }}
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
                <span>{{ submitting ? t('employees.saving') : primaryLabel }}</span>
              </button>
            </footer>
          </form>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.emp-drawer-enter-active,
.emp-drawer-leave-active {
  pointer-events: none;
}

.emp-drawer-enter-active .emp-drawer-backdrop {
  transition: opacity 200ms ease;
}

.emp-drawer-leave-active .emp-drawer-backdrop {
  transition: opacity 180ms ease;
}

.emp-drawer-enter-from .emp-drawer-backdrop,
.emp-drawer-leave-to .emp-drawer-backdrop {
  opacity: 0;
}

.emp-drawer-enter-active .emp-drawer-panel {
  transition:
    transform 280ms cubic-bezier(0.22, 1, 0.36, 1),
    opacity 280ms cubic-bezier(0.22, 1, 0.36, 1);
}

.emp-drawer-leave-active .emp-drawer-panel {
  transition:
    transform 220ms cubic-bezier(0.22, 1, 0.36, 1),
    opacity 220ms cubic-bezier(0.22, 1, 0.36, 1);
}

.emp-drawer-enter-from .emp-drawer-panel,
.emp-drawer-leave-to .emp-drawer-panel {
  transform: translateX(100%);
  opacity: 0.92;
}

.emp-drawer-enter-to .emp-drawer-panel,
.emp-drawer-leave-from .emp-drawer-panel {
  transform: translateX(0);
  opacity: 1;
}

[dir='ltr'] .emp-drawer-enter-from .emp-drawer-panel,
[dir='ltr'] .emp-drawer-leave-to .emp-drawer-panel {
  transform: translateX(-100%);
}

@media (prefers-reduced-motion: reduce) {
  .emp-drawer-enter-active .emp-drawer-backdrop,
  .emp-drawer-leave-active .emp-drawer-backdrop,
  .emp-drawer-enter-active .emp-drawer-panel,
  .emp-drawer-leave-active .emp-drawer-panel {
    transition: none;
  }

  .emp-drawer-enter-from .emp-drawer-panel,
  .emp-drawer-leave-to .emp-drawer-panel,
  [dir='ltr'] .emp-drawer-enter-from .emp-drawer-panel,
  [dir='ltr'] .emp-drawer-leave-to .emp-drawer-panel {
    transform: none;
    opacity: 1;
  }
}
</style>
