<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { Loader2, X } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppDateTimeInput from '@/shared/components/AppDateTimeInput.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import {
  useCancelMeetingMutation,
  useCompleteMeetingMutation,
  useDeleteMeetingMutation,
  useRescheduleMeetingMutation,
  useScheduleMeetingMutation,
  useStartMeetingMutation,
} from '../mutations/useMeetingMutations'
import type { Meeting, MeetingLifecycleAction } from '../types/meetings'
import {
  availableLifecycleActions,
  fromDatetimeLocalValue,
  mapMeetingErrorCode,
  toDatetimeLocalValue,
} from '../validation/meetingValidation'

const props = defineProps<{
  meeting: Meeting
}>()

const emit = defineEmits<{
  refreshed: []
}>()

const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const { confirm } = useConfirm()
const { can } = usePermissions()

const scheduleMutation = useScheduleMeetingMutation()
const rescheduleMutation = useRescheduleMeetingMutation()
const startMutation = useStartMeetingMutation()
const completeMutation = useCompleteMeetingMutation()
const cancelMutation = useCancelMeetingMutation()
const deleteMutation = useDeleteMeetingMutation()

const dialogOpen = ref(false)
const dialogAction = ref<MeetingLifecycleAction | null>(null)
const dialogDatetime = ref('')
const dialogComment = ref('')
const dialogError = ref('')
const actionError = ref('')

const actions = computed(() =>
  availableLifecycleActions(props.meeting.status).filter((item) => can(item.permission)),
)

const canDelete = computed(
  () => props.meeting.status === 'draft' && can('meetings.update'),
)

const isPending = computed(
  () =>
    scheduleMutation.isPending.value ||
    rescheduleMutation.isPending.value ||
    startMutation.isPending.value ||
    completeMutation.isPending.value ||
    cancelMutation.isPending.value ||
    deleteMutation.isPending.value,
)

const dialogNeedsDatetime = computed(() => {
  const action = dialogAction.value
  return action === 'schedule' || action === 'reschedule'
})

const dialogNeedsComment = computed(() => dialogAction.value === 'cancel')

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

function actionLabel(action: MeetingLifecycleAction): string {
  return t(`meetings.lifecycle.${action}`)
}

function openDialog(action: MeetingLifecycleAction): void {
  dialogAction.value = action
  dialogDatetime.value = toDatetimeLocalValue(props.meeting.scheduled_at)
  dialogComment.value = ''
  dialogError.value = ''
  dialogOpen.value = true
}

function closeDialog(): void {
  dialogOpen.value = false
  dialogAction.value = null
  dialogDatetime.value = ''
  dialogComment.value = ''
  dialogError.value = ''
}

async function runAction(
  action: MeetingLifecycleAction,
  options?: { scheduledAt?: string; comment?: string | null },
): Promise<void> {
  actionError.value = ''
  const id = props.meeting.id

  try {
    switch (action) {
      case 'schedule':
        await scheduleMutation.mutateAsync({
          id,
          payload: { scheduled_at: options?.scheduledAt ?? '' },
        })
        toast.success(t('meetings.toasts.scheduled'))
        break
      case 'reschedule':
        await rescheduleMutation.mutateAsync({
          id,
          payload: {
            scheduled_at: options?.scheduledAt ?? '',
            comment: options?.comment || null,
          },
        })
        toast.success(t('meetings.toasts.rescheduled'))
        break
      case 'start':
        await startMutation.mutateAsync({ id })
        toast.success(t('meetings.toasts.started'))
        break
      case 'complete':
        await completeMutation.mutateAsync({
          id,
          payload: options?.comment ? { comment: options.comment } : {},
        })
        toast.success(t('meetings.toasts.completed'))
        break
      case 'cancel':
        await cancelMutation.mutateAsync({
          id,
          payload: { comment: options?.comment ?? '' },
        })
        toast.success(t('meetings.toasts.cancelled'))
        break
    }
    emit('refreshed')
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(apiMessage(error))
  }
}

async function handleAction(action: MeetingLifecycleAction): Promise<void> {
  const def = actions.value.find((item) => item.action === action)
  if (!def) return

  if (def.requiresComment || def.requiresDatetime) {
    openDialog(action)
    return
  }

  if (action === 'start' || action === 'complete') {
    const confirmed = await confirm({
      title: t(`meetings.confirm.${action}.title`),
      message: t(`meetings.confirm.${action}.body`),
      confirmLabel: t(`meetings.confirm.${action}.cta`),
      cancelLabel: t('meetings.cancel'),
      variant: action === 'complete' ? 'warning' : 'primary',
    })
    if (!confirmed) return
  }

  await runAction(action)
}

async function submitDialogAction(): Promise<void> {
  if (!dialogAction.value) return

  if (dialogNeedsDatetime.value) {
    const iso = fromDatetimeLocalValue(dialogDatetime.value)
    if (!iso) {
      dialogError.value = t('meetings.validation.scheduledAtRequired')
      return
    }
  }

  if (dialogNeedsComment.value) {
    const trimmed = dialogComment.value.trim()
    if (!trimmed) {
      dialogError.value = t('meetings.validation.commentRequired')
      return
    }
  }

  const action = dialogAction.value
  const scheduledAt = fromDatetimeLocalValue(dialogDatetime.value) ?? undefined
  const comment = dialogComment.value.trim() || null
  closeDialog()
  await runAction(action, { scheduledAt, comment })
}

async function handleDelete(): Promise<void> {
  const confirmed = await confirm({
    title: t('meetings.confirmDeleteTitle'),
    message: t('meetings.confirmDeleteBody'),
    confirmLabel: t('meetings.confirmDeleteCta'),
    cancelLabel: t('meetings.cancel'),
    variant: 'danger',
  })
  if (!confirmed) return
  try {
    await deleteMutation.mutateAsync(props.meeting.id)
    toast.success(t('meetings.toasts.deleted'))
    await router.push('/app/meetings')
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function actionButtonClass(action: MeetingLifecycleAction): string {
  if (action === 'cancel') {
    return 'border-red-200 bg-red-50 text-red-800 hover:bg-red-100'
  }
  if (action === 'complete') {
    return 'border-emerald-200 bg-emerald-50 text-emerald-900 hover:bg-emerald-100'
  }
  if (action === 'reschedule') {
    return 'border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100'
  }
  return 'border-brand-border bg-brand-surface text-brand-primary-dark hover:bg-brand-primary-soft'
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex flex-col gap-2">
      <button
        v-for="item in actions"
        :key="item.action"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border px-4 text-sm font-semibold transition disabled:opacity-50 xl:justify-start"
        :class="actionButtonClass(item.action)"
        :disabled="isPending"
        @click="handleAction(item.action)"
      >
        <Loader2 v-if="isPending" class="h-3.5 w-3.5 animate-spin" />
        {{ actionLabel(item.action) }}
      </button>

      <PermissionGuard v-if="canDelete" permission="meetings.update">
        <button
          type="button"
          class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-red-200 bg-white px-4 text-sm font-semibold text-red-700 transition hover:bg-red-50 disabled:opacity-50 xl:justify-start"
          :disabled="isPending"
          @click="handleDelete"
        >
          {{ t('meetings.actions.delete') }}
        </button>
      </PermissionGuard>
    </div>

    <p
      v-if="actionError"
      class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-700"
      role="alert"
    >
      {{ actionError }}
    </p>

    <Teleport to="body">
      <div
        v-if="dialogOpen"
        class="fixed inset-0 z-[460] flex items-center justify-center p-4"
        role="presentation"
      >
        <div
          class="absolute inset-0 bg-[rgba(15,23,20,0.38)]"
          aria-hidden="true"
          @click="closeDialog"
        />
        <div
          class="relative w-full max-w-[420px] rounded-2xl border border-brand-border bg-brand-surface shadow-xl"
          role="dialog"
          aria-modal="true"
          @click.stop
        >
          <button
            type="button"
            class="absolute end-3 top-3 inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-text-muted hover:bg-brand-bg"
            :aria-label="t('meetings.closeDrawer')"
            @click="closeDialog"
          >
            <X class="h-4 w-4" />
          </button>
          <div class="px-6 pb-4 pt-6">
            <h3 class="text-lg font-bold text-brand-text">
              {{
                dialogAction
                  ? t(`meetings.confirm.${dialogAction}.title`)
                  : t('meetings.commentRequiredTitle')
              }}
            </h3>
            <p class="mt-2 text-sm text-brand-text-secondary">
              {{
                dialogAction
                  ? t(`meetings.confirm.${dialogAction}.body`)
                  : t('meetings.commentRequiredBody')
              }}
            </p>

            <label v-if="dialogNeedsDatetime" class="mt-4 block">
              <span class="mb-1.5 block text-sm font-semibold text-brand-text">
                {{ t('meetings.fields.scheduledAt') }}
              </span>
              <AppDateTimeInput v-model="dialogDatetime" :placeholder="t('meetings.fields.scheduledAt')" />
            </label>

            <label
              v-if="dialogNeedsComment || dialogAction === 'reschedule'"
              class="mt-4 block"
            >
              <span class="mb-1.5 block text-sm font-semibold text-brand-text">
                {{
                  dialogNeedsComment
                    ? t('meetings.fields.cancelReason')
                    : t('meetings.fields.comment')
                }}
              </span>
              <textarea
                v-model="dialogComment"
                rows="3"
                class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
                :placeholder="t('meetings.commentPlaceholder')"
              />
            </label>

            <p v-if="dialogError" class="mt-1.5 text-xs text-red-600">{{ dialogError }}</p>
          </div>
          <div
            class="flex items-center justify-end gap-2 border-t border-brand-border bg-[#F7F8F6] px-6 py-4"
          >
            <button
              type="button"
              class="inline-flex h-10 items-center rounded-[10px] border border-brand-border px-4 text-sm font-semibold"
              @click="closeDialog"
            >
              {{ t('meetings.cancel') }}
            </button>
            <button
              type="button"
              class="inline-flex h-10 items-center rounded-[10px] bg-brand-primary-dark px-4 text-sm font-semibold text-white"
              :disabled="isPending"
              @click="submitDialogAction"
            >
              {{
                dialogAction
                  ? t(`meetings.confirm.${dialogAction}.cta`)
                  : t('meetings.confirmGeneric')
              }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
