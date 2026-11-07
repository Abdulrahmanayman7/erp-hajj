<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { Loader2 } from 'lucide-vue-next'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { ApiError } from '@/shared/api/http'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import { useAddComplaintNoteMutation } from '../mutations/useComplaintMutations'
import type { OperationalComplaint } from '../types/complaints'
import { canAddComplaintNote, mapComplaintErrorCode } from '../validation/complaintValidation'

const props = defineProps<{
  complaint: OperationalComplaint
}>()

const emit = defineEmits<{ refreshed: [] }>()

const { t } = useI18n()
const { can } = usePermissions()
const toast = useToast()
const seasonStore = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(seasonStore)
const addNote = useAddComplaintNoteMutation()
const notePending = computed(() => addNote.isPending.value)

const body = ref('')
const error = ref('')

async function submit(): Promise<void> {
  error.value = ''
  if (!body.value.trim()) {
    error.value = t('complaints.validation.required')
    return
  }
  if (!can('complaints.add_note') || !canAddComplaintNote(props.complaint.status)) return
  try {
    await addNote.mutateAsync({
      id: props.complaint.id,
      payload: { body: body.value.trim() },
      seasonId: selectedSeasonId.value,
    })
    body.value = ''
    toast.success(t('complaints.toasts.noteAdded'))
    emit('refreshed')
  } catch (err) {
    if (err instanceof ApiError) {
      const mapped = mapComplaintErrorCode(err.code)
      error.value =
        mapped !== 'generic' ? t(`complaints.errors.${mapped}`) : err.message || t('complaints.errors.generic')
    } else {
      error.value = t('complaints.errors.generic')
    }
    toast.error(error.value)
  }
}

function formatWhen(iso: string | null): string {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', { dateStyle: 'medium', timeStyle: 'short' }).format(
      new Date(iso),
    )
  } catch {
    return iso
  }
}
</script>

<template>
  <section class="space-y-4 rounded-2xl border border-brand-border bg-brand-surface p-4" data-testid="complaint-notes-panel">
    <h3 class="text-base font-bold text-brand-text">{{ t('complaints.tabs.notes') }}</h3>

    <ul v-if="(complaint.notes?.length ?? 0) > 0" class="space-y-3">
      <li
        v-for="note in complaint.notes"
        :key="note.id"
        class="rounded-xl border border-brand-border bg-brand-bg p-3"
      >
        <p class="whitespace-pre-wrap text-sm text-brand-text">{{ note.body }}</p>
        <p class="mt-2 text-xs text-brand-text-secondary">
          {{ note.created_by?.name ?? '—' }} · {{ formatWhen(note.created_at) }}
        </p>
      </li>
    </ul>
    <p v-else class="text-sm text-brand-text-secondary">{{ t('complaints.notesEmpty') }}</p>

    <div
      v-if="can('complaints.add_note') && canAddComplaintNote(complaint.status)"
      class="space-y-2"
    >
      <label class="block">
        <span class="mb-1.5 block text-sm font-semibold">{{ t('complaints.fields.noteBody') }}</span>
        <textarea
          v-model="body"
          rows="3"
          class="w-full rounded-xl border border-brand-border p-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
          data-testid="complaint-note-input"
        />
      </label>
      <button
        type="button"
        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
        :disabled="notePending"
        data-testid="complaint-note-submit"
        @click="submit"
      >
        <Loader2 v-if="notePending" class="h-4 w-4 animate-spin" aria-hidden="true" />
        {{ t('complaints.actions.addNote') }}
      </button>
      <p v-if="error" class="text-sm text-red-700" role="alert">{{ error }}</p>
    </div>
  </section>
</template>
