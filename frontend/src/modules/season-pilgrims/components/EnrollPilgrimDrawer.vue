<script setup lang="ts">
import { computed, nextTick, onUnmounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
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
}>()

const { t } = useI18n()
const toast = useToast()
const form = reactive<EnrollFormState>(emptyEnrollForm())
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
let previouslyFocused: HTMLElement | null = null

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
  async (isOpen) => {
    if (isOpen) {
      resetForm()
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

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.open && !submitting.value) {
    event.preventDefault()
    emit('close')
  }
}

onUnmounted(() => document.removeEventListener('keydown', onKeydown))

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
  <Teleport to="body">
    <Transition name="enroll-drawer">
      <div v-if="open" class="fixed inset-0 z-50" role="presentation">
        <div class="absolute inset-0 bg-[rgba(15,23,20,0.32)]" @click="emit('close')" />
        <aside
          class="absolute inset-y-0 start-0 flex h-dvh w-full max-w-[480px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
          role="dialog"
          aria-modal="true"
          :aria-label="t('seasonOps.enroll.title')"
          @click.stop
        >
          <header
            class="flex shrink-0 items-start justify-between gap-4 border-b border-brand-border px-4 py-5 sm:px-7"
          >
            <div class="min-w-0 text-start">
              <h3 class="text-[21px] font-bold text-brand-text">
                {{ t('seasonOps.enroll.title') }}
              </h3>
              <p class="mt-1.5 text-[13px] text-brand-text-secondary">
                {{ t('seasonOps.enroll.subtitle') }}
              </p>
            </div>
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-[10px] text-brand-text-secondary hover:bg-brand-bg"
              :disabled="submitting"
              :aria-label="t('pilgrims.closeDrawer')"
              @click="emit('close')"
            >
              <X class="h-5 w-5" />
            </button>
          </header>

          <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
            <div class="flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-7">
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
                  :selected-option="
                    presetPilgrim
                      ? pilgrimOption(presetPilgrim)
                      : null
                  "
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
                <span class="text-xs text-brand-text-muted">{{
                  t('seasonOps.enroll.familyHint')
                }}</span>
              </label>
            </div>

            <footer
              class="flex shrink-0 items-center justify-end gap-3 border-t border-brand-border px-4 py-4 sm:px-7"
            >
              <button
                type="button"
                class="inline-flex h-11 items-center rounded-xl border border-brand-border px-4 text-sm font-semibold"
                :disabled="submitting"
                @click="emit('close')"
              >
                {{ t('pilgrims.cancel') }}
              </button>
              <button
                type="submit"
                class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-60"
                :disabled="submitting || !seasonWritable || !seasonId"
              >
                <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                <span>{{ t('seasonOps.enroll.submit') }}</span>
              </button>
            </footer>
          </form>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.enroll-drawer-enter-active,
.enroll-drawer-leave-active {
  transition: opacity 0.18s ease;
}
.enroll-drawer-enter-active aside,
.enroll-drawer-leave-active aside {
  transition: transform 0.22s ease;
}
.enroll-drawer-enter-from,
.enroll-drawer-leave-to {
  opacity: 0;
}
.enroll-drawer-enter-from aside,
.enroll-drawer-leave-to aside {
  transform: translateX(100%);
}
</style>
