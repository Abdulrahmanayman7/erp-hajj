<script setup lang="ts">
import { computed, nextTick, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import { listUsers } from '@/modules/users/api/usersApi'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import { toSelectId, userSelectOption } from '@/shared/lookups/selectOptions'

import type { Employee } from '../types/employees'

const props = defineProps<{
  open: boolean
  employee: Employee | null
  userId: number | ''
  formError: string
  submitting: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
  unlink: []
  'update:userId': [number | '']
}>()

const { t } = useI18n()
let previouslyFocused: HTMLElement | null = null

const title = computed(() => t('employees.userLinkTitle'))
const hasLinkedUser = computed(() => props.employee?.user != null)
const fetchActiveUsers = (params: { search?: string; page: number; per_page: number }) =>
  listUsers({ ...params, status: 'active' })
const emptyUser = computed(() => ({ value: '', label: t('employees.selectUser') }))
const selectedUser = computed(() =>
  props.employee?.user ? userSelectOption(props.employee.user) : null,
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
              {{ t('employees.userLinkSubtitle', { name: employee.full_name }) }}
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

        <p
          class="mt-3 rounded-xl border border-brand-border bg-[#F7F8F6] px-3.5 py-3 text-[13px] leading-relaxed text-brand-text-secondary"
        >
          {{ t('employees.userLinkHelper') }}
        </p>

        <div v-if="hasLinkedUser" class="mt-4 rounded-xl border border-brand-border px-3.5 py-3">
          <p class="text-xs font-semibold text-brand-text-muted">
            {{ t('employees.currentLinkedUser') }}
          </p>
          <p class="mt-1 text-sm font-semibold text-brand-text">{{ employee.user?.name }}</p>
          <p class="text-xs text-brand-text-secondary" dir="ltr">{{ employee.user?.email }}</p>
        </div>

        <div class="mt-4">
          <span class="mb-1.5 block text-sm font-medium text-brand-text">
            {{ t('employees.fields.user') }}
          </span>
          <AppRemoteSelect
            :model-value="userId"
            query-key="users-active"
            :fetcher="fetchActiveUsers"
            :map-option="userSelectOption"
            :empty-option="emptyUser"
            :selected-option="selectedUser"
            :placeholder="t('employees.selectUser')"
            :disabled="submitting"
            :enabled="open"
            @update:model-value="emit('update:userId', toSelectId($event))"
          />
          <p class="mt-2 text-xs leading-relaxed text-brand-text-muted">
            {{ t('employees.userLinkNoCreate') }}
          </p>
        </div>

        <p
          v-if="formError"
          class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          role="alert"
        >
          {{ formError }}
        </p>

        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
          <button
            v-if="hasLinkedUser"
            type="button"
            class="inline-flex h-11 items-center justify-center rounded-xl border border-amber-200 px-4 text-sm font-semibold text-amber-800 hover:bg-amber-50 disabled:opacity-60 sm:me-auto"
            :disabled="submitting"
            @click="emit('unlink')"
          >
            {{ t('employees.unlinkUserCta') }}
          </button>
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
            :disabled="submitting || userId === ''"
            @click="emit('submit')"
          >
            <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
            <span>{{ t('employees.userLinkCta') }}</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
