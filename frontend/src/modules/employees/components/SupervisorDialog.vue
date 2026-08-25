<script setup lang="ts">
import { computed, nextTick, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import { listEmployees } from '@/modules/employees/api/employeesApi'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import { employeeSelectOption, toSelectId } from '@/shared/lookups/selectOptions'

import type { Employee } from '../types/employees'

const props = defineProps<{
  open: boolean
  employee: Employee | null
  supervisorId: number | ''
  formError: string
  submitting: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:supervisorId': [number | '']
}>()

const { t } = useI18n()
let previouslyFocused: HTMLElement | null = null

const title = computed(() => t('employees.supervisorTitle'))
const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })
const emptySupervisor = computed(() => ({ value: '', label: t('employees.noSupervisor') }))
const selectedSupervisor = computed(() =>
  props.employee?.supervisor ? employeeSelectOption(props.employee.supervisor) : null,
)
const excludeSelf = computed(() => (props.employee ? [props.employee.id] : []))

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
    <div
      v-if="open && employee"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[rgba(15,23,20,0.32)] p-4"
      role="presentation"
      @click.self="emit('close')"
    >
      <div
        class="w-full max-w-md rounded-2xl bg-brand-surface p-4 shadow-xl sm:p-5"
        style="padding-bottom: max(16px, env(safe-area-inset-bottom))"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        @click.stop
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <h3 class="text-lg font-semibold text-brand-text">{{ title }}</h3>
            <p class="mt-1 text-sm text-brand-text-secondary">
              {{ t('employees.supervisorSubtitle', { name: employee.full_name }) }}
            </p>
          </div>
          <button
            type="button"
            class="rounded-lg p-2 text-brand-text-muted hover:bg-brand-bg"
            :aria-label="t('employees.closeDrawer')"
            :disabled="submitting"
            @click="emit('close')"
          >
            <X class="h-4 w-4" />
          </button>
        </div>

        <div class="mt-4">
          <span class="mb-1.5 block text-sm font-medium text-brand-text">
            {{ t('employees.fields.supervisor') }}
          </span>
          <AppRemoteSelect
            :model-value="supervisorId"
            query-key="employees-active"
            :fetcher="fetchActiveEmployees"
            :map-option="employeeSelectOption"
            :empty-option="emptySupervisor"
            :selected-option="selectedSupervisor"
            :exclude-values="excludeSelf"
            :placeholder="t('employees.selectSupervisor')"
            :disabled="submitting"
            :enabled="open"
            @update:model-value="emit('update:supervisorId', toSelectId($event))"
          />
          <p class="mt-2 text-xs leading-relaxed text-brand-text-muted">
            {{ t('employees.supervisorHint') }}
          </p>
        </div>

        <p
          v-if="formError"
          class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          role="alert"
        >
          {{ formError }}
        </p>

        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button
            type="button"
            class="inline-flex h-11 items-center justify-center rounded-xl px-4 text-sm font-semibold text-brand-text-secondary hover:bg-brand-bg"
            :disabled="submitting"
            @click="emit('close')"
          >
            {{ t('employees.cancel') }}
          </button>
          <button
            type="button"
            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-60"
            :disabled="submitting"
            @click="emit('submit')"
          >
            <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
            <span>{{ t('employees.supervisorCta') }}</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
