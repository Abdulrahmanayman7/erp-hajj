<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, Pencil, UserPlus } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import EnrollPilgrimDrawer from '../components/EnrollPilgrimDrawer.vue'
import PilgrimFormDrawer from '../components/PilgrimFormDrawer.vue'
import {
  useActivateSeasonPilgrimMutation,
  useAssignPilgrimGroupMutation,
  useCancelSeasonPilgrimMutation,
  useChangeProgramMutation,
  useCompleteSeasonPilgrimMutation,
  useDeleteSeasonPilgrimMutation,
  useSetFamilyGroupMutation,
} from '../mutations/useEnrollmentMutations'
import { useUpdatePilgrimMutation } from '../mutations/usePilgrimMutations'
import { usePilgrimQuery, useSeasonPilgrimsQuery } from '../queries/usePilgrimsQuery'
import {
  useFamilyGroupDetailQuery,
  useFamilyGroupsQuery,
  usePilgrimGroupsQuery,
  useSeasonProgramsQuery,
} from '../queries/useSeasonOpsQueries'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useSeasonsQuery } from '../queries/useSeasonsQuery'
import type { ParticipationEditState } from '../types/enrollment'
import type { PilgrimFormState } from '../types/pilgrims'
import {
  canRevealIdentityDocumentNumbers,
  emptyPilgrimForm,
  validatePilgrimForm,
} from '../validation/pilgrimValidation'
import { emptyParticipationEdit, mapEnrollmentErrorCode } from '../validation/enrollmentValidation'

const { t } = useI18n()
const { can, permissions } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()
const route = useRoute()
const router = useRouter()
const seasonStore = useAccommodationSeasonStore()

const pilgrimId = computed(() => {
  const raw = Number(route.params.id)
  return Number.isFinite(raw) && raw > 0 ? raw : null
})

const selectedSeasonId = ref<number | ''>(seasonStore.selectedSeasonId ?? '')

const { data, isLoading, isError, refetch } = usePilgrimQuery(pilgrimId)

const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const selectedSeason = computed(
  () =>
    (seasonsQuery.data.value?.data ?? []).find((season) => season.id === selectedSeasonId.value) ??
    null,
)
const seasonWritable = computed(
  () => selectedSeason.value != null && selectedSeason.value.status !== 'closed',
)

const participationParams = computed(() => ({
  pilgrim_id: pilgrimId.value ?? undefined,
  per_page: 5,
  seasonId: selectedSeasonId.value === '' ? null : Number(selectedSeasonId.value),
}))
const canLoadParticipation = computed(
  () =>
    can('season_pilgrims.view') &&
    pilgrimId.value != null &&
    selectedSeasonId.value !== '',
)
const participationQuery = useSeasonPilgrimsQuery(participationParams, canLoadParticipation)
const participation = computed(() => participationQuery.data.value?.data?.[0] ?? null)

const programsQuery = useSeasonProgramsQuery(
  () => (selectedSeasonId.value === '' ? null : Number(selectedSeasonId.value)),
  () => canLoadParticipation.value && can('season_programs.view'),
)
const groupsQuery = usePilgrimGroupsQuery(
  () => (selectedSeasonId.value === '' ? null : Number(selectedSeasonId.value)),
  () => canLoadParticipation.value && can('season_pilgrim_groups.view'),
)
const familiesQuery = useFamilyGroupsQuery(
  () => (selectedSeasonId.value === '' ? null : Number(selectedSeasonId.value)),
  () => canLoadParticipation.value && can('season_family_groups.view'),
)

const familyDetailId = computed(() => participation.value?.family_group?.id ?? null)
const familyDetailQuery = useFamilyGroupDetailQuery(
  familyDetailId,
  () => (selectedSeasonId.value === '' ? null : Number(selectedSeasonId.value)),
  () =>
    canLoadParticipation.value &&
    can('season_family_groups.view') &&
    familyDetailId.value != null,
)

const linkedGroup = computed(() => {
  const groupId = participation.value?.pilgrim_group?.id
  if (groupId == null) return null
  return (groupsQuery.data.value ?? []).find((row) => row.id === groupId) ?? null
})

const revealDocs = computed(() => canRevealIdentityDocumentNumbers(permissions.value ?? []))

const activateMutation = useActivateSeasonPilgrimMutation()
const completeMutation = useCompleteSeasonPilgrimMutation()
const cancelMutation = useCancelSeasonPilgrimMutation()
const deleteParticipationMutation = useDeleteSeasonPilgrimMutation()
const lifecycleBusy = computed(
  () =>
    activateMutation.isPending.value ||
    completeMutation.isPending.value ||
    cancelMutation.isPending.value ||
    deleteParticipationMutation.isPending.value,
)

const seasonOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('pilgrims.profile.selectSeason') },
  ...(seasonsQuery.data.value?.data ?? []).map((season) => ({
    value: season.id,
    label: `${season.name} (${season.code})`,
  })),
])

const programOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonOps.enroll.noProgram') },
  ...(programsQuery.data.value ?? []).map((row) => ({
    value: row.id,
    label: row.name,
    hint: row.code,
  })),
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

const drawerOpen = ref(false)
const enrollOpen = ref(false)
const editingParticipation = ref(false)
const participationForm = reactive<ParticipationEditState>(emptyParticipationEdit())
const participationError = ref('')
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<PilgrimFormState>(emptyPilgrimForm())
const updateMutation = useUpdatePilgrimMutation()
const changeProgramMutation = useChangeProgramMutation()
const assignGroupMutation = useAssignPilgrimGroupMutation()
const setFamilyMutation = useSetFamilyGroupMutation()
const savingParticipation = computed(
  () =>
    changeProgramMutation.isPending.value ||
    assignGroupMutation.isPending.value ||
    setFamilyMutation.isPending.value,
)

watch(
  () => seasonStore.selectedSeasonId,
  (id) => {
    if (id != null && selectedSeasonId.value === '') {
      selectedSeasonId.value = id
    }
  },
  { immediate: true },
)

watch(selectedSeasonId, (id) => {
  editingParticipation.value = false
  if (id !== '' && seasonStore.selectedSeasonId !== Number(id)) {
    seasonStore.setSelectedSeasonId(Number(id))
  }
})

watch(
  participation,
  (row) => {
    if (!row) return
    Object.assign(
      participationForm,
      emptyParticipationEdit({
        season_program_id: row.program?.id ?? '',
        season_pilgrim_group_id: row.pilgrim_group?.id ?? '',
        season_family_group_id: row.family_group?.id ?? '',
      }),
    )
  },
  { immediate: true },
)

function genderLabel(value: string | null | undefined): string {
  if (value === 'male' || value === 'female') return t(`pilgrims.gender.${value}`)
  return t('pilgrims.gender.none')
}

function openEdit(): void {
  const pilgrim = data.value
  if (!pilgrim) return
  Object.assign(form, {
    full_name: pilgrim.full_name,
    gender: pilgrim.gender ?? '',
    nationality: pilgrim.nationality ?? '',
    phone: pilgrim.phone ?? '',
    notes: pilgrim.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  drawerOpen.value = true
}

async function submitForm(): Promise<void> {
  const pilgrim = data.value
  if (!pilgrim) return
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  formError.value = ''
  const local = validatePilgrimForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length > 0) return

  try {
    await updateMutation.mutateAsync({
      id: pilgrim.id,
      payload: {
        full_name: form.full_name.trim(),
        gender: form.gender === '' ? null : form.gender,
        nationality: form.nationality.trim() ? form.nationality.trim().toUpperCase() : null,
        phone: form.phone.trim() || null,
        notes: form.notes.trim() || null,
      },
    })
    toast.success(t('pilgrims.toasts.updated'))
    drawerOpen.value = false
    await refetch()
  } catch (error) {
    if (error instanceof ApiError) {
      formError.value = error.message || t('pilgrims.errors.generic')
      return
    }
    formError.value = t('pilgrims.errors.generic')
  }
}

async function runLifecycle(
  action: 'activate' | 'complete' | 'cancel' | 'delete',
): Promise<void> {
  if (!participation.value || selectedSeasonId.value === '') return
  const id = participation.value.id
  const seasonId = Number(selectedSeasonId.value)

  if (action === 'cancel') {
    const reason = window.prompt(t('pilgrims.profile.cancelReasonPrompt'))
    if (reason == null) return
    if (reason.trim().length < 3) {
      toast.error(t('pilgrims.profile.cancelReasonRequired'))
      return
    }
    const ok = await confirm({
      title: t('pilgrims.profile.confirmCancelTitle'),
      message: t('pilgrims.profile.confirmCancelBody'),
      confirmLabel: t('pilgrims.profile.cancelParticipation'),
      cancelLabel: t('pilgrims.cancel'),
      variant: 'danger',
    })
    if (!ok) return
    try {
      await cancelMutation.mutateAsync({ id, reason: reason.trim(), seasonId })
      toast.success(t('pilgrims.profile.toasts.cancelled'))
      await participationQuery.refetch()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t('seasonOps.errors.generic'))
    }
    return
  }

  if (action === 'delete') {
    const ok = await confirm({
      title: t('pilgrims.profile.confirmDeleteTitle'),
      message: t('pilgrims.profile.confirmDeleteBody'),
      confirmLabel: t('pilgrims.profile.deleteParticipation'),
      cancelLabel: t('pilgrims.cancel'),
      variant: 'danger',
    })
    if (!ok) return
    try {
      await deleteParticipationMutation.mutateAsync({ id, seasonId })
      toast.success(t('pilgrims.profile.toasts.deleted'))
      await participationQuery.refetch()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t('seasonOps.errors.generic'))
    }
    return
  }

  const ok = await confirm({
    title:
      action === 'activate'
        ? t('pilgrims.profile.confirmActivateTitle')
        : t('pilgrims.profile.confirmCompleteTitle'),
    message:
      action === 'activate'
        ? t('pilgrims.profile.confirmActivateBody')
        : t('pilgrims.profile.confirmCompleteBody'),
    confirmLabel:
      action === 'activate'
        ? t('pilgrims.profile.activateParticipation')
        : t('pilgrims.profile.completeParticipation'),
    cancelLabel: t('pilgrims.cancel'),
    variant: 'primary',
  })
  if (!ok) return

  try {
    if (action === 'activate') {
      await activateMutation.mutateAsync({ id, seasonId })
      toast.success(t('pilgrims.profile.toasts.activated'))
    } else {
      await completeMutation.mutateAsync({ id, seasonId })
      toast.success(t('pilgrims.profile.toasts.completed'))
    }
    await participationQuery.refetch()
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : t('seasonOps.errors.generic'))
  }
}

async function saveParticipation(): Promise<void> {
  if (!participation.value || selectedSeasonId.value === '' || !seasonWritable.value) return
  participationError.value = ''
  const id = participation.value.id
  const seasonId = Number(selectedSeasonId.value)

  try {
    const currentProgram = participation.value.program?.id ?? null
    const nextProgram =
      participationForm.season_program_id === ''
        ? null
        : Number(participationForm.season_program_id)
    if (currentProgram !== nextProgram) {
      if (!can('season_pilgrims.change_program')) {
        participationError.value = t('seasonOps.errors.generic')
        return
      }
      await changeProgramMutation.mutateAsync({
        id,
        seasonProgramId: nextProgram,
        reason: participationForm.program_reason.trim() || null,
        seasonId,
      })
    }

    const currentGroup = participation.value.pilgrim_group?.id ?? null
    const nextGroup =
      participationForm.season_pilgrim_group_id === ''
        ? null
        : Number(participationForm.season_pilgrim_group_id)
    if (currentGroup !== nextGroup) {
      if (!can('season_pilgrim_groups.assign_members')) {
        participationError.value = t('seasonOps.errors.generic')
        return
      }
      await assignGroupMutation.mutateAsync({
        id,
        seasonPilgrimGroupId: nextGroup,
        seasonId,
      })
    }

    const currentFamily = participation.value.family_group?.id ?? null
    const nextFamily =
      participationForm.season_family_group_id === ''
        ? null
        : Number(participationForm.season_family_group_id)
    if (currentFamily !== nextFamily) {
      if (!can('season_family_groups.assign_members')) {
        participationError.value = t('seasonOps.errors.generic')
        return
      }
      await setFamilyMutation.mutateAsync({
        id,
        seasonFamilyGroupId: nextFamily,
        seasonId,
      })
    }

    toast.success(t('pilgrims.profile.participationUpdated'))
    editingParticipation.value = false
    await participationQuery.refetch()
  } catch (error) {
    if (error instanceof ApiError) {
      const mapped = mapEnrollmentErrorCode(error.code)
      participationError.value =
        mapped !== 'generic'
          ? t(`seasonOps.errors.${mapped}`)
          : error.message || t('seasonOps.errors.generic')
      return
    }
    participationError.value = t('seasonOps.errors.generic')
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader :title="t('pilgrims.profile.title')" :subtitle="t('pilgrims.profile.subtitle')">
      <template #actions>
        <button
          type="button"
          class="inline-flex h-11 items-center gap-2 rounded-xl border border-brand-border px-4 text-sm font-semibold"
          @click="router.push('/app/pilgrims')"
        >
          <ArrowRight class="h-4 w-4" />
          {{ t('pilgrims.profile.back') }}
        </button>
        <PermissionGuard permission="season_pilgrims.register">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl border border-brand-border px-4 text-sm font-semibold"
            :disabled="!data"
            @click="enrollOpen = true"
          >
            <UserPlus class="h-4 w-4" />
            {{ t('pilgrims.profile.enrollInSeason') }}
          </button>
        </PermissionGuard>
        <PermissionGuard permission="pilgrims.update">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white"
            :disabled="!data"
            @click="openEdit"
          >
            <Pencil class="h-4 w-4" />
            {{ t('pilgrims.edit') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('pilgrims.loading') }}
    </div>
    <div
      v-else-if="isError || !data"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('pilgrims.errors.loadProfile') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('pilgrims.retry') }}
      </button>
    </div>
    <template v-else>
      <section class="rounded-2xl border border-brand-border bg-brand-surface p-5 sm:p-6">
        <h2 class="text-base font-bold text-brand-text">{{ t('pilgrims.profile.basics') }}</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-semibold text-brand-text-muted">
              {{ t('pilgrims.fields.fullName') }}
            </dt>
            <dd class="mt-1 text-sm font-semibold text-brand-text">{{ data.full_name }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold text-brand-text-muted">
              {{ t('pilgrims.fields.gender') }}
            </dt>
            <dd class="mt-1 text-sm text-brand-text">{{ genderLabel(data.gender) }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold text-brand-text-muted">
              {{ t('pilgrims.fields.nationality') }}
            </dt>
            <dd class="mt-1 font-mono text-sm text-brand-text" dir="ltr">
              {{ data.nationality ?? '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-semibold text-brand-text-muted">
              {{ t('pilgrims.fields.phone') }}
            </dt>
            <dd class="mt-1 text-sm text-brand-text" dir="ltr">{{ data.phone ?? '—' }}</dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-xs font-semibold text-brand-text-muted">
              {{ t('pilgrims.fields.notes') }}
            </dt>
            <dd class="mt-1 whitespace-pre-wrap text-sm text-brand-text-secondary">
              {{ data.notes || '—' }}
            </dd>
          </div>
        </dl>
      </section>

      <section class="rounded-2xl border border-brand-border bg-brand-surface p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-base font-bold text-brand-text">
            {{ t('pilgrims.profile.seasonParticipation') }}
          </h2>
          <div class="flex flex-wrap items-center gap-2">
            <AppSelect
              v-if="can('seasons.view') || can('season_pilgrims.view')"
              v-model="selectedSeasonId"
              :options="seasonOptions"
              searchable
              class="min-w-56"
            />
            <button
              v-if="
                participation &&
                seasonWritable &&
                (can('season_pilgrims.change_program') ||
                  can('season_pilgrim_groups.assign_members') ||
                  can('season_family_groups.assign_members'))
              "
              type="button"
              class="inline-flex h-10 items-center rounded-xl border border-brand-border px-3 text-xs font-semibold"
              @click="editingParticipation = !editingParticipation"
            >
              {{ t('pilgrims.profile.editParticipation') }}
            </button>
          </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2 text-xs">
          <RouterLink
            v-if="can('season_pilgrim_groups.view')"
            class="rounded-lg bg-brand-bg px-2 py-1 font-semibold text-brand-primary-dark"
            to="/app/season-groups"
          >
            {{ t('pilgrims.profile.manageGroups') }}
          </RouterLink>
          <RouterLink
            v-if="can('season_programs.view')"
            class="rounded-lg bg-brand-bg px-2 py-1 font-semibold text-brand-primary-dark"
            to="/app/season-programs"
          >
            {{ t('pilgrims.profile.managePrograms') }}
          </RouterLink>
          <RouterLink
            v-if="can('season_family_groups.view')"
            class="rounded-lg bg-brand-bg px-2 py-1 font-semibold text-brand-primary-dark"
            to="/app/season-families"
          >
            {{ t('pilgrims.profile.manageFamilies') }}
          </RouterLink>
        </div>

        <p
          v-if="!can('season_pilgrims.view')"
          class="mt-4 rounded-xl bg-brand-bg px-3 py-2 text-sm text-brand-text-secondary"
        >
          {{ t('pilgrims.profile.seasonPermMissing') }}
        </p>
        <p
          v-else-if="selectedSeasonId === ''"
          class="mt-4 rounded-xl bg-brand-bg px-3 py-2 text-sm text-brand-text-secondary"
        >
          {{ t('pilgrims.profile.seasonRequired') }}
        </p>
        <p
          v-else-if="participationQuery.isLoading.value"
          class="mt-4 text-sm text-brand-text-muted"
        >
          {{ t('pilgrims.profile.participationLoading') }}
        </p>
        <p
          v-else-if="participationQuery.isError.value"
          class="mt-4 text-sm text-red-700"
        >
          {{ t('pilgrims.profile.participationError') }}
        </p>
        <div
          v-else-if="!participation"
          class="mt-4 space-y-3 rounded-xl bg-brand-bg px-3 py-3 text-sm text-brand-text-secondary"
        >
          <p>{{ t('pilgrims.profile.noParticipation') }}</p>
          <PermissionGuard permission="season_pilgrims.register">
            <button
              type="button"
              class="inline-flex h-9 items-center gap-2 rounded-xl bg-brand-primary-dark px-3 text-xs font-semibold text-white disabled:opacity-50"
              :disabled="!seasonWritable"
              @click="enrollOpen = true"
            >
              <UserPlus class="h-3.5 w-3.5" />
              {{ t('pilgrims.profile.enrollInSeason') }}
            </button>
          </PermissionGuard>
        </div>
        <template v-else>
          <div v-if="!editingParticipation" class="mt-4 space-y-4">
            <dl class="grid gap-4 sm:grid-cols-2">
              <div>
                <dt class="text-xs font-semibold text-brand-text-muted">
                  {{ t('pilgrims.profile.reference') }}
                </dt>
                <dd class="mt-1 font-mono text-sm" dir="ltr">{{ participation.reference_number }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-brand-text-muted">
                  {{ t('pilgrims.profile.status') }}
                </dt>
                <dd class="mt-1 text-sm">
                  {{ t(`pilgrims.seasonStatus.${participation.status}`) }}
                </dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-brand-text-muted">
                  {{ t('pilgrims.profile.group') }}
                </dt>
                <dd class="mt-1 text-sm">{{ participation.pilgrim_group?.name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-brand-text-muted">
                  {{ t('pilgrims.profile.program') }}
                </dt>
                <dd class="mt-1 text-sm">{{ participation.program?.name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-brand-text-muted">
                  {{ t('pilgrims.profile.family') }}
                </dt>
                <dd class="mt-1 text-sm">{{ participation.family_group?.name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-brand-text-muted">
                  {{ t('pilgrims.profile.supervisor') }}
                </dt>
                <dd class="mt-1 text-sm text-brand-text-secondary">
                  <template v-if="linkedGroup?.supervisors?.length">
                    <ul class="space-y-1">
                      <li
                        v-for="supervisor in linkedGroup.supervisors"
                        :key="supervisor.id"
                      >
                        {{ supervisor.employee?.full_name ?? '—' }}
                        <span class="text-xs">({{ supervisor.role }})</span>
                      </li>
                    </ul>
                  </template>
                  <template v-else-if="participation.pilgrim_group">
                    {{ t('pilgrims.profile.supervisorNone') }}
                  </template>
                  <template v-else>
                    {{ t('pilgrims.profile.supervisorUnavailable') }}
                  </template>
                </dd>
              </div>
            </dl>

            <div>
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('pilgrims.profile.familyMembers') }}
              </h3>
              <p
                v-if="!participation.family_group"
                class="mt-2 text-sm text-brand-text-muted"
              >
                —
              </p>
              <p
                v-else-if="familyDetailQuery.isLoading.value"
                class="mt-2 text-sm text-brand-text-muted"
              >
                {{ t('seasonOps.families.membersLoading') }}
              </p>
              <p
                v-else-if="familyDetailQuery.isError.value"
                class="mt-2 text-sm text-red-700"
              >
                {{ t('seasonOps.families.errors.members') }}
              </p>
              <ul
                v-else-if="familyDetailQuery.data.value?.members?.length"
                class="mt-2 space-y-1"
              >
                <li
                  v-for="member in familyDetailQuery.data.value.members"
                  :key="member.season_pilgrim_id"
                  class="rounded-lg border border-brand-border px-3 py-2 text-sm"
                >
                  {{ member.pilgrim?.full_name ?? '—' }}
                  <span
                    v-if="member.is_primary_member"
                    class="ms-2 text-xs font-semibold text-emerald-700"
                  >
                    {{ t('seasonOps.families.primary') }}
                  </span>
                </li>
              </ul>
              <p v-else class="mt-2 text-sm text-brand-text-muted">
                {{ t('seasonOps.families.membersEmpty') }}
              </p>
            </div>

            <div class="flex flex-wrap gap-2 border-t border-brand-border pt-4">
              <PermissionGuard permission="season_pilgrims.activate">
                <button
                  v-if="participation.status === 'registered'"
                  type="button"
                  class="inline-flex h-9 items-center rounded-xl bg-emerald-700 px-3 text-xs font-semibold text-white disabled:opacity-50"
                  :disabled="!seasonWritable || lifecycleBusy"
                  @click="runLifecycle('activate')"
                >
                  {{ t('pilgrims.profile.activateParticipation') }}
                </button>
              </PermissionGuard>
              <PermissionGuard permission="season_pilgrims.complete">
                <button
                  v-if="participation.status === 'active'"
                  type="button"
                  class="inline-flex h-9 items-center rounded-xl bg-brand-primary-dark px-3 text-xs font-semibold text-white disabled:opacity-50"
                  :disabled="!seasonWritable || lifecycleBusy"
                  @click="runLifecycle('complete')"
                >
                  {{ t('pilgrims.profile.completeParticipation') }}
                </button>
              </PermissionGuard>
              <PermissionGuard permission="season_pilgrims.cancel">
                <button
                  v-if="participation.status === 'registered' || participation.status === 'active'"
                  type="button"
                  class="inline-flex h-9 items-center rounded-xl border border-amber-300 px-3 text-xs font-semibold text-amber-900 disabled:opacity-50"
                  :disabled="!seasonWritable || lifecycleBusy"
                  @click="runLifecycle('cancel')"
                >
                  {{ t('pilgrims.profile.cancelParticipation') }}
                </button>
              </PermissionGuard>
              <PermissionGuard permission="season_pilgrims.delete">
                <button
                  v-if="participation.status === 'registered'"
                  type="button"
                  class="inline-flex h-9 items-center rounded-xl border border-red-200 px-3 text-xs font-semibold text-red-700 disabled:opacity-50"
                  :disabled="!seasonWritable || lifecycleBusy"
                  @click="runLifecycle('delete')"
                >
                  {{ t('pilgrims.profile.deleteParticipation') }}
                </button>
              </PermissionGuard>
            </div>
          </div>

          <form v-else class="mt-4 space-y-4" @submit.prevent="saveParticipation">
            <p
              v-if="!seasonWritable"
              class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900"
            >
              {{ t('seasonOps.bar.closedNotice') }}
            </p>
            <p
              v-if="participationError"
              class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700"
            >
              {{ participationError }}
            </p>
            <label v-if="can('season_pilgrims.change_program')" class="block space-y-1.5">
              <span class="text-sm font-semibold">{{ t('pilgrims.profile.program') }}</span>
              <AppSelect
                v-model="participationForm.season_program_id"
                :options="programOptions"
                searchable
              />
            </label>
            <label v-if="can('season_pilgrims.change_program')" class="block space-y-1.5">
              <span class="text-sm font-semibold">{{ t('pilgrims.profile.programReason') }}</span>
              <input
                v-model="participationForm.program_reason"
                type="text"
                maxlength="1000"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
            </label>
            <label
              v-if="can('season_pilgrim_groups.assign_members')"
              class="block space-y-1.5"
            >
              <span class="text-sm font-semibold">{{ t('pilgrims.profile.group') }}</span>
              <AppSelect
                v-model="participationForm.season_pilgrim_group_id"
                :options="groupOptions"
                searchable
              />
            </label>
            <label
              v-if="can('season_family_groups.assign_members')"
              class="block space-y-1.5"
            >
              <span class="text-sm font-semibold">{{ t('pilgrims.profile.family') }}</span>
              <AppSelect
                v-model="participationForm.season_family_group_id"
                :options="familyOptions"
                searchable
              />
            </label>
            <div class="flex justify-end gap-2">
              <button
                type="button"
                class="h-10 rounded-xl border border-brand-border px-3 text-sm font-semibold"
                @click="editingParticipation = false"
              >
                {{ t('pilgrims.cancel') }}
              </button>
              <button
                type="submit"
                class="h-10 rounded-xl bg-brand-primary-dark px-3 text-sm font-semibold text-white disabled:opacity-60"
                :disabled="savingParticipation || !seasonWritable"
              >
                {{ t('pilgrims.profile.saveParticipation') }}
              </button>
            </div>
          </form>
        </template>
      </section>

      <section class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-brand-border bg-brand-surface p-5">
          <h2 class="text-base font-bold text-brand-text">{{ t('pilgrims.profile.transport') }}</h2>
          <p class="mt-3 text-sm text-brand-text-secondary">
            {{ t('pilgrims.profile.transportUnavailable') }}
          </p>
        </div>
        <div class="rounded-2xl border border-brand-border bg-brand-surface p-5">
          <h2 class="text-base font-bold text-brand-text">{{ t('pilgrims.profile.housing') }}</h2>
          <p class="mt-3 text-sm text-brand-text-secondary">
            {{ t('pilgrims.profile.housingUnavailable') }}
          </p>
        </div>
      </section>

      <section class="rounded-2xl border border-brand-border bg-brand-surface p-5 sm:p-6">
        <h2 class="text-base font-bold text-brand-text">{{ t('pilgrims.profile.identityDocs') }}</h2>
        <p
          v-if="!revealDocs"
          class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900"
        >
          {{ t('pilgrims.docs.hidden') }}
          <span v-if="data.has_identity_documents" class="ms-1 font-semibold">
            ({{ t('pilgrims.docs.yes') }})
          </span>
        </p>
        <p
          v-else-if="!data.identity_documents?.length"
          class="mt-3 text-sm text-brand-text-muted"
        >
          {{ t('pilgrims.profile.noDocs') }}
        </p>
        <ul v-else class="mt-3 space-y-2">
          <li
            v-for="doc in data.identity_documents"
            :key="doc.id"
            class="rounded-xl border border-brand-border px-3 py-2 text-sm"
          >
            <div class="flex flex-wrap gap-x-4 gap-y-1">
              <span
                >{{ t('pilgrims.profile.docType') }}:
                <strong>{{ doc.document_type }}</strong></span
              >
              <span
                >{{ t('pilgrims.profile.docCountry') }}:
                <strong dir="ltr">{{ doc.issuing_country }}</strong></span
              >
              <span
                >{{ t('pilgrims.profile.docNumber') }}:
                <strong dir="ltr">{{ doc.document_number }}</strong></span
              >
            </div>
          </li>
        </ul>
      </section>
    </template>

    <PilgrimFormDrawer
      :open="drawerOpen"
      :editing="data ?? null"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="updateMutation.isPending.value"
      @close="drawerOpen = false"
      @update:form="Object.assign(form, $event)"
      @submit="submitForm"
    />

    <EnrollPilgrimDrawer
      :open="enrollOpen"
      :season-id="selectedSeasonId === '' ? null : Number(selectedSeasonId)"
      :season-writable="seasonWritable"
      :preset-pilgrim="data ?? null"
      @close="enrollOpen = false"
      @enrolled="() => participationQuery.refetch()"
    />
  </div>
</template>
