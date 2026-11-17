<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { ApiError } from '@/shared/api/http'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useCaptureOnOpen, useFormDirty } from '@/shared/composables/useFormDirty'
import { toSelectId } from '@/shared/lookups/selectOptions'
import { useToast } from '@/shared/composables/useToast'

import { listPilgrims } from '../api/pilgrimsApi'
import {
  useRegisterSeasonPilgrimMutation,
  useSetFamilyGroupMutation,
} from '../mutations/useEnrollmentMutations'
import {
  useFamilyGroupsQuery,
  usePilgrimGroupsQuery,
  useSeasonProgramsQuery,
} from '../queries/useSeasonOpsQueries'
import type { EnrollFormState } from '../types/enrollment'
import type { Pilgrim } from '../types/pilgrims'
import {
  emptyEnrollForm,
  mapEnrollmentErrorCode,
  validateEnrollForm,
} from '../validation/enrollmentValidation'

const props = defineProps<{
  open: boolean
  seasonId: number | null
  seasonWritable: boolean
  presetPilgrim?: Pilgrim | null
}>()

const emit = defineEmits<{
  close: []
  enrolled: [pilgrimId: number]
  'update:open': [value: boolean]
}>()

const { t } = useI18n()
const toast = useToast()
const form = reactive<EnrollFormState>(emptyEnrollForm())
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})

const { dirty, capture, reset } = useFormDirty(() => ({ ...form }))
useCaptureOnOpen(() => props.open, capture, { onClose: () => reset() })

const registerMutation = useRegisterSeasonPilgrimMutation()
const familyMutation = useSetFamilyGroupMutation()

const programsQuery = useSeasonProgramsQuery(
  () => props.seasonId,
  () => props.open && props.seasonId != null,
)
const groupsQuery = usePilgrimGroupsQuery(
  () => props.seasonId,
  () => props.open && props.seasonId != null,
)
const familiesQuery = useFamilyGroupsQuery(
  () => props.seasonId,
  () => props.open && props.seasonId != null,
)

const programOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonOps.enroll.noProgram') },
  ...(programsQuery.data.value ?? [])
    .filter((row) => row.is_active)
    .map((row) => ({ value: row.id, label: row.name, hint: row.code })),
])

const groupOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonOps.enroll.noGroup') },
  ...(groupsQuery.data.value ?? []).map((row) => ({
    value: row.id,
    label: row.name,
    hint: row.code,
  })),
])

const familyOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonOps.enroll.noFamily') },
  ...(familiesQuery.data.value ?? []).map((row) => ({
    value: row.id,
    label: row.name,
  })),
])

const submitting = computed(
  () => registerMutation.isPending.value || familyMutation.isPending.value,
)

function pilgrimOption(pilgrim: { id: number; full_name: string }) {
  return { value: pilgrim.id, label: pilgrim.full_name }
}

const fetchPilgrims = (params: { search?: string; page: number; per_page: number }) =>
  listPilgrims(params)

function fieldMessage(key: string): string {
  const code = fieldErrors[key]
  return code ? t(`seasonOps.errors.validation.${code}`) : ''
}

function resetForm(): void {
  Object.assign(form, emptyEnrollForm(props.presetPilgrim?.id ?? ''))
  formError.value = ''
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) resetForm()
  },
)

function onOpenUpdate(value: boolean): void {
  emit('update:open', value)
  if (!value) emit('close')
}

async function submit(): Promise<void> {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  formError.value = ''

  if (!props.seasonId) {
    formError.value = t('seasonOps.errors.seasonRequired')
    return
  }
  if (!props.seasonWritable) {
    formError.value = t('seasonOps.errors.seasonClosed')
    return
  }

  const local = validateEnrollForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length > 0) return

  try {
    const enrolled = await registerMutation.mutateAsync({
      seasonId: props.seasonId,
      payload: {
        pilgrim_id: Number(form.pilgrim_id),
        season_program_id:
          form.season_program_id === '' ? null : Number(form.season_program_id),
        season_pilgrim_group_id:
          form.season_pilgrim_group_id === '' ? null : Number(form.season_pilgrim_group_id),
      },
    })

    if (form.season_family_group_id !== '') {
      await familyMutation.mutateAsync({
        id: enrolled.id,
        seasonFamilyGroupId: Number(form.season_family_group_id),
        seasonId: props.seasonId,
      })
    }

    toast.success(t('seasonOps.enroll.toasts.success'))
    emit('enrolled', Number(form.pilgrim_id))
    emit('update:open', false)
    emit('close')
  } catch (error) {
    if (error instanceof ApiError) {
      const mapped = mapEnrollmentErrorCode(error.code)
      formError.value =
        mapped !== 'generic'
          ? t(`seasonOps.errors.${mapped}`)
          : error.message || t('seasonOps.errors.generic')
      if (error.errors) {
        Object.keys(error.errors).forEach((key) => {
          fieldErrors[key] = 'format'
        })
      }
      return
    }
    formError.value = t('seasonOps.errors.generic')
  }
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="t('seasonOps.enroll.title')"
    :description="t('seasonOps.enroll.subtitle')"
    size="sm"
    :dirty="dirty"
    :submitting="submitting"
    :submit-disabled="!seasonWritable || !seasonId"
    :cancel-label="t('pilgrims.cancel')"
    :submit-label="t('seasonOps.enroll.submit')"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="submit"
  >
    <p
      v-if="!seasonWritable"
      class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
    >
      {{ t('seasonOps.bar.closedNotice') }}
    </p>
    <p
      v-if="formError"
      class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ formError }}
    </p>

    <label class="block space-y-1.5">
      <span class="text-sm font-semibold">{{ t('seasonOps.enroll.pilgrim') }}</span>
      <AppRemoteSelect
        :model-value="form.pilgrim_id"
        query-key="enroll-pilgrims"
        :fetcher="fetchPilgrims"
        :map-option="pilgrimOption"
        :selected-option="presetPilgrim ? pilgrimOption(presetPilgrim) : null"
        :disabled="presetPilgrim != null || submitting || !seasonWritable"
        @update:model-value="form.pilgrim_id = toSelectId($event)"
      />
      <span v-if="fieldMessage('pilgrim_id')" class="text-xs text-red-600">{{
        fieldMessage('pilgrim_id')
      }}</span>
    </label>

    <label class="block space-y-1.5">
      <span class="text-sm font-semibold">{{ t('seasonOps.enroll.program') }}</span>
      <AppSelect
        v-model="form.season_program_id"
        :options="programOptions"
        :disabled="submitting || !seasonWritable"
        searchable
      />
    </label>

    <label class="block space-y-1.5">
      <span class="text-sm font-semibold">{{ t('seasonOps.enroll.group') }}</span>
      <AppSelect
        v-model="form.season_pilgrim_group_id"
        :options="groupOptions"
        :disabled="submitting || !seasonWritable"
        searchable
      />
    </label>

    <label class="block space-y-1.5">
      <span class="text-sm font-semibold">{{ t('seasonOps.enroll.family') }}</span>
      <AppSelect
        v-model="form.season_family_group_id"
        :options="familyOptions"
        :disabled="submitting || !seasonWritable"
        searchable
      />
      <span class="text-xs text-brand-text-muted">{{ t('seasonOps.enroll.familyHint') }}</span>
    </label>
  </AppFormDrawer>
</template>