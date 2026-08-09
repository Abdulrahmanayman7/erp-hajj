<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2 } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import { useUpdateMinutesMutation } from '../mutations/useMeetingMutations'
import type { Meeting } from '../types/meetings'
import { isMeetingLocked, mapMeetingErrorCode } from '../validation/meetingValidation'

const props = defineProps<{
  meeting: Meeting
}>()

const emit = defineEmits<{
  refreshed: []
}>()

const { t } = useI18n()
const toast = useToast()
const { can } = usePermissions()
const updateMutation = useUpdateMinutesMutation()

const minutesBody = ref(props.meeting.minutes_body ?? '')
const formError = ref('')
const isSaving = computed(() => updateMutation.isPending.value)

watch(
  () => props.meeting.minutes_body,
  (value) => {
    minutesBody.value = value ?? ''
  },
)

const readOnly = computed(() => isMeetingLocked(props.meeting.status))
const canManage = computed(() => can('meetings.manage_minutes') && !readOnly.value)

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return t('meetings.errors.generic')
  }
  const mapped = mapMeetingErrorCode(error.code)
  if (mapped !== 'generic') {
    return t(`meetings.errors.${mapped}`)
  }
  return error.message || t('meetings.errors.generic')
}

function formatUpdatedAt(): string {
  if (!props.meeting.updated_at) return ''
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(props.meeting.updated_at))
  } catch {
    return props.meeting.updated_at
  }
}

async function saveMinutes(): Promise<void> {
  formError.value = ''
  try {
    await updateMutation.mutateAsync({
      id: props.meeting.id,
      payload: { minutes_body: minutesBody.value },
    })
    toast.success(t('meetings.toasts.minutesSaved'))
    emit('refreshed')
  } catch (error) {
    formError.value = apiMessage(error)
  }
}
</script>

<template>
  <div class="rounded-2xl border border-brand-border bg-brand-surface p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h3 class="text-base font-bold text-brand-text">{{ t('meetings.sections.minutes') }}</h3>
        <p class="mt-1 text-sm text-brand-text-secondary">{{ t('meetings.minutesSubtitle') }}</p>
        <p v-if="meeting.updated_at" class="mt-1 text-xs text-brand-text-muted">
          {{ t('meetings.lastUpdated', { at: formatUpdatedAt() }) }}
        </p>
      </div>
      <PermissionGuard v-if="canManage" permission="meetings.manage_minutes">
        <button
          type="button"
          class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
          :disabled="isSaving"
          @click="saveMinutes"
        >
          <Loader2
            v-if="isSaving"
            class="h-4 w-4 animate-spin"
          />
          {{ t('meetings.actions.saveMinutes') }}
        </button>
      </PermissionGuard>
    </div>

    <label class="mt-4 block">
      <span class="sr-only">{{ t('meetings.fields.minutesBody') }}</span>
      <textarea
        v-model="minutesBody"
        rows="10"
        class="w-full rounded-xl border border-brand-border bg-brand-surface px-3.5 py-3 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-80"
        :placeholder="t('meetings.minutesPlaceholder')"
        :disabled="!canManage || isSaving"
      />
    </label>

    <p v-if="formError" class="mt-2 text-sm text-red-700" role="alert">{{ formError }}</p>
    <p v-if="readOnly" class="mt-2 text-xs text-brand-text-muted">
      {{ t('meetings.readOnlyHint') }}
    </p>
  </div>
</template>
