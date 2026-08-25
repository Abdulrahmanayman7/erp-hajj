<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import { listUsers } from '@/modules/users/api/usersApi'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { toSelectNullableId, userSelectOption } from '@/shared/lookups/selectOptions'

import type { OrganizationUnit, OrganizationUnitType } from '../types/organization'

export interface OrganizationUnitFormState {
  name: string
  code: string
  type: OrganizationUnitType | ''
  parent_id: number | null
  manager_user_id: number | null
  sort_order: number
}

const props = defineProps<{
  open: boolean
  editing: OrganizationUnit | null
  form: OrganizationUnitFormState
  formError: string
  submitting: boolean
  parentOptions: AppSelectOption[]
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [OrganizationUnitFormState]
}>()

const { t } = useI18n()
const nameInputRef = ref<HTMLInputElement | null>(null)
let previouslyFocused: HTMLElement | null = null

const isEdit = computed(() => props.editing != null)
const fetchActiveUsers = (params: { search?: string; page: number; per_page: number }) =>
  listUsers({ ...params, status: 'active' })
const emptyManager = computed(() => ({ value: '', label: t('organization.noManager') }))
const selectedManager = computed(() =>
  props.editing?.manager ? userSelectOption(props.editing.manager) : null,
)
const title = computed(() =>
  isEdit.value ? t('organization.editTitle') : t('organization.createTitle'),
)
const subtitle = computed(() =>
  isEdit.value ? t('organization.editSubtitle') : t('organization.createSubtitle'),
)

const typeOptions = computed<AppSelectOption[]>(() => [
  { value: 'department', label: t('organization.types.department') },
  { value: 'section', label: t('organization.types.section') },
  { value: 'unit', label: t('organization.types.unit') },
])

function patch(partial: Partial<OrganizationUnitFormState>): void {
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
    <Transition name="org-drawer">
      <div v-if="open" class="org-drawer-root fixed inset-0 z-50" role="presentation">
        <div
          class="absolute inset-0 bg-[rgba(15,23,20,0.32)]"
          aria-hidden="true"
          @click="emit('close')"
        />

        <aside
          class="org-drawer-panel absolute inset-y-0 start-0 flex h-dvh w-full max-w-[480px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
          role="dialog"
          aria-modal="true"
          :aria-label="title"
        >
          <header class="flex shrink-0 items-start justify-between gap-3 border-b border-brand-border/70 px-4 py-4">
            <div>
              <h2 class="text-lg font-semibold text-brand-ink">{{ title }}</h2>
              <p class="mt-1 text-sm text-brand-muted">{{ subtitle }}</p>
            </div>
            <button
              type="button"
              class="rounded-lg p-2 text-brand-muted hover:bg-brand-canvas hover:text-brand-ink"
              :aria-label="t('organization.close')"
              :disabled="submitting"
              @click="emit('close')"
            >
              <X class="h-5 w-5" />
            </button>
          </header>

          <div class="flex-1 overflow-y-auto px-4 py-4">
            <p v-if="formError" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ formError }}
            </p>

            <div class="space-y-4">
              <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-brand-ink">{{
                  t('organization.fields.name')
                }}</span>
                <input
                  ref="nameInputRef"
                  :value="form.name"
                  type="text"
                  class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-ink outline-none focus:border-brand-primary"
                  @input="patch({ name: ($event.target as HTMLInputElement).value })"
                />
              </label>

              <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-brand-ink">{{
                  t('organization.fields.code')
                }}</span>
                <input
                  :value="form.code"
                  type="text"
                  class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 font-mono text-sm uppercase text-brand-ink outline-none focus:border-brand-primary disabled:bg-brand-canvas"
                  :disabled="isEdit"
                  :readonly="isEdit"
                  @input="
                    patch({
                      code: ($event.target as HTMLInputElement).value.toUpperCase(),
                    })
                  "
                />
                <span v-if="isEdit" class="mt-1 block text-xs text-brand-muted">{{
                  t('organization.codeImmutable')
                }}</span>
              </label>

              <div>
                <span class="mb-1.5 block text-sm font-medium text-brand-ink">{{
                  t('organization.fields.type')
                }}</span>
                <AppSelect
                  :model-value="form.type || null"
                  :options="typeOptions"
                  :placeholder="t('organization.selectType')"
                  @update:model-value="patch({ type: ($event as OrganizationUnitType) || '' })"
                />
              </div>

              <div v-if="!isEdit">
                <span class="mb-1.5 block text-sm font-medium text-brand-ink">{{
                  t('organization.fields.parent')
                }}</span>
                <AppSelect
                  :model-value="form.parent_id"
                  :options="parentOptions"
                  searchable
                  :placeholder="t('organization.rootParent')"
                  @update:model-value="
                    patch({
                      parent_id: $event === null || $event === '' ? null : Number($event),
                    })
                  "
                />
              </div>

              <div>
                <span class="mb-1.5 block text-sm font-medium text-brand-ink">{{
                  t('organization.fields.manager')
                }}</span>
                <AppRemoteSelect
                  :model-value="form.manager_user_id"
                  query-key="users-active"
                  :fetcher="fetchActiveUsers"
                  :map-option="userSelectOption"
                  :empty-option="emptyManager"
                  :selected-option="selectedManager"
                  :placeholder="t('organization.noManager')"
                  :enabled="open"
                  @update:model-value="patch({ manager_user_id: toSelectNullableId($event) })"
                />
                <span class="mt-1 block text-xs text-brand-muted">{{
                  t('organization.managerHint')
                }}</span>
              </div>

              <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-brand-ink">{{
                  t('organization.fields.sortOrder')
                }}</span>
                <input
                  :value="form.sort_order"
                  type="number"
                  min="0"
                  class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-ink outline-none focus:border-brand-primary"
                  @input="
                    patch({
                      sort_order: Number(($event.target as HTMLInputElement).value || 0),
                    })
                  "
                />
              </label>
            </div>
          </div>

          <footer
            class="flex shrink-0 flex-col-reverse gap-2 border-t border-brand-border/70 px-4 py-3 sm:flex-row sm:justify-end"
            style="padding-bottom: max(12px, env(safe-area-inset-bottom))"
          >
            <button
              type="button"
              class="inline-flex h-11 items-center justify-center rounded-xl px-4 text-sm font-medium text-brand-muted hover:bg-brand-canvas"
              :disabled="submitting"
              @click="emit('close')"
            >
              {{ t('organization.cancel') }}
            </button>
            <button
              type="button"
              class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary px-4 text-sm font-semibold text-white hover:opacity-95 disabled:opacity-60"
              :disabled="submitting"
              @click="emit('submit')"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              {{ isEdit ? t('organization.save') : t('organization.createCta') }}
            </button>
          </footer>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.org-drawer-enter-active,
.org-drawer-leave-active {
  transition: opacity 0.2s ease;
}
.org-drawer-enter-active .org-drawer-panel,
.org-drawer-leave-active .org-drawer-panel {
  transition: transform 0.22s ease;
}
.org-drawer-enter-from,
.org-drawer-leave-to {
  opacity: 0;
}
.org-drawer-enter-from .org-drawer-panel,
.org-drawer-leave-to .org-drawer-panel {
  transform: translateX(100%);
}
[dir='rtl'] .org-drawer-enter-from .org-drawer-panel,
[dir='rtl'] .org-drawer-leave-to .org-drawer-panel {
  transform: translateX(-100%);
}
</style>
