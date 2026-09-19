<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, Pencil, Plus, Trash2 } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import {
  useCreateAgendaItemMutation,
  useDeleteAgendaItemMutation,
  useUpdateAgendaItemMutation,
} from '../mutations/useMeetingMutations'
import type { Meeting, MeetingAgendaItem } from '../types/meetings'
import {
  isMeetingLocked,
  mapMeetingErrorCode,
  validateAgendaItemForm,
} from '../validation/meetingValidation'

const props = defineProps<{
  meeting: Meeting
}>()

const emit = defineEmits<{
  refreshed: []
}>()

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const { can } = usePermissions()

const createMutation = useCreateAgendaItemMutation()
const updateMutation = useUpdateAgendaItemMutation()
const deleteMutation = useDeleteAgendaItemMutation()

const formOpen = ref(false)
const editing = ref<MeetingAgendaItem | null>(null)
const formError = ref('')
const form = reactive({ title: '', description: '' })

const readOnly = computed(() => isMeetingLocked(props.meeting.status))
const canManage = computed(() => can('meetings.update') && !readOnly.value)

const items = computed(() =>
  [...(props.meeting.agenda_items ?? [])].sort((a, b) => a.sort_order - b.sort_order),
)

const isPending = computed(
  () =>
    createMutation.isPending.value ||
    updateMutation.isPending.value ||
    deleteMutation.isPending.value,
)

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

function openCreate(): void {
  editing.value = null
  form.title = ''
  form.description = ''
  formError.value = ''
  formOpen.value = true
}

function openEdit(item: MeetingAgendaItem): void {
  editing.value = item
  form.title = item.title
  form.description = item.description ?? ''
  formError.value = ''
  formOpen.value = true
}

function closeForm(): void {
  formOpen.value = false
  editing.value = null
}

async function submitForm(): Promise<void> {
  formError.value = ''
  const validation = validateAgendaItemForm(form)
  if (validation.title) {
    formError.value = t('meetings.validation.required')
    return
  }

  const payload = {
    title: form.title.trim(),
    description: form.description.trim() || null,
  }

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        meetingId: props.meeting.id,
        itemId: editing.value.id,
        payload,
      })
      toast.success(t('meetings.toasts.agendaUpdated'))
    } else {
      await createMutation.mutateAsync({
        meetingId: props.meeting.id,
        payload,
      })
      toast.success(t('meetings.toasts.agendaCreated'))
    }
    closeForm()
    emit('refreshed')
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function removeItem(item: MeetingAgendaItem): Promise<void> {
  const confirmed = await confirm({
    title: t('meetings.confirmDeleteAgendaTitle'),
    message: t('meetings.confirmDeleteAgendaBody'),
    confirmLabel: t('meetings.actions.delete'),
    cancelLabel: t('meetings.cancel'),
    variant: 'danger',
  })
  if (!confirmed) return
  try {
    await deleteMutation.mutateAsync({
      meetingId: props.meeting.id,
      itemId: item.id,
    })
    toast.success(t('meetings.toasts.agendaDeleted'))
    emit('refreshed')
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <div class="rounded-2xl border border-brand-border bg-brand-surface p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="text-base font-bold text-brand-text">{{ t('meetings.sections.agenda') }}</h3>
        <p class="mt-1 text-sm text-brand-text-secondary">{{ t('meetings.agendaSubtitle') }}</p>
      </div>
      <PermissionGuard v-if="canManage" permission="meetings.update">
        <button
          type="button"
          class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-primary-dark hover:bg-brand-primary-soft"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('meetings.actions.addAgenda') }}
        </button>
      </PermissionGuard>
    </div>

    <form
      v-if="formOpen"
      class="mt-4 space-y-3 rounded-xl border border-brand-border bg-brand-bg p-4"
      v-autofocus-when
      @submit.prevent="submitForm"
    >
      <label class="block">
        <span class="mb-1.5 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.agendaTitle') }}
        </span>
        <input
          v-model="form.title"
          type="text"
          class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
          :disabled="isPending"
        />
      </label>
      <label class="block">
        <span class="mb-1.5 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.agendaDescription') }}
        </span>
        <textarea
          v-model="form.description"
          rows="2"
          class="w-full rounded-xl border border-brand-border bg-brand-surface px-3 py-2 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
          :disabled="isPending"
        />
      </label>
      <p v-if="formError" class="text-xs text-red-600" role="alert">{{ formError }}</p>
      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="inline-flex h-9 items-center rounded-lg border border-brand-border px-3 text-sm font-semibold"
          :disabled="isPending"
          @click="closeForm"
        >
          {{ t('meetings.cancel') }}
        </button>
        <button
          type="submit"
          class="inline-flex h-9 items-center gap-2 rounded-lg bg-brand-primary-dark px-3 text-sm font-semibold text-white"
          :disabled="isPending"
        >
          <Loader2 v-if="isPending" class="h-3.5 w-3.5 animate-spin" />
          {{ editing ? t('meetings.save') : t('meetings.actions.addAgenda') }}
        </button>
      </div>
    </form>

    <div
      v-if="items.length === 0"
      class="mt-6 rounded-xl border border-dashed border-brand-border px-4 py-8 text-center text-sm text-brand-text-muted"
    >
      {{ t('meetings.agendaEmpty') }}
    </div>

    <ol v-else class="mt-4 space-y-3">
      <li
        v-for="(item, index) in items"
        :key="item.id"
        class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-brand-border/80 bg-[#FAFBFA] p-4"
      >
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold text-brand-text">
            <span class="me-2 text-brand-text-muted">{{ index + 1 }}.</span>
            {{ item.title }}
          </p>
          <p
            v-if="item.description"
            class="mt-1 whitespace-pre-wrap text-sm text-brand-text-secondary"
          >
            {{ item.description }}
          </p>
        </div>
        <div v-if="canManage" class="flex items-center gap-1">
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-primary-dark hover:bg-brand-primary-soft"
            :aria-label="t('meetings.actions.edit')"
            :disabled="isPending"
            @click="openEdit(item)"
          >
            <Pencil class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-700 hover:bg-red-50"
            :aria-label="t('meetings.actions.delete')"
            :disabled="isPending"
            @click="removeItem(item)"
          >
            <Trash2 class="h-4 w-4" />
          </button>
        </div>
      </li>
    </ol>
  </div>
</template>
