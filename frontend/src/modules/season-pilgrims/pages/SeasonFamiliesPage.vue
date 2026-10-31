<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { Pencil, Plus, Users } from 'lucide-vue-next'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useToast } from '@/shared/composables/useToast'

import SeasonWorkspaceBar from '../components/SeasonWorkspaceBar.vue'
import {
  useCreateFamilyGroupMutation,
  useDeleteFamilyGroupMutation,
  useSetFamilyPrimaryMutation,
  useUpdateFamilyGroupMutation,
} from '../mutations/useSeasonOpsMutations'
import {
  useFamilyGroupDetailQuery,
  useFamilyGroupsQuery,
} from '../queries/useSeasonOpsQueries'
import { useSeasonsQuery } from '../queries/useSeasonsQuery'
import type { FamilyFormState, SeasonFamilyGroup } from '../types/families'
import {
  resolveSeasonScopedListState,
  validateFamilyForm,
} from '../validation/enrollmentValidation'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const store = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(store)

const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const selectedSeason = computed(
  () => (seasonsQuery.data.value?.data ?? []).find((s) => s.id === selectedSeasonId.value) ?? null,
)
const seasonWritable = computed(() => selectedSeason.value?.status !== 'closed')

const { data, isLoading, isError, refetch } = useFamilyGroupsQuery(
  selectedSeasonId,
  () => selectedSeasonId.value != null,
)
const families = computed(() => data.value ?? [])
const listState = computed(() =>
  resolveSeasonScopedListState({
    hasSeason: selectedSeasonId.value != null,
    isLoading: isLoading.value,
    isError: isError.value,
    count: families.value.length,
  }),
)

const selectedFamilyId = ref<number | null>(null)
const detailQuery = useFamilyGroupDetailQuery(
  selectedFamilyId,
  selectedSeasonId,
  () => selectedFamilyId.value != null && selectedSeasonId.value != null,
)

watch(selectedSeasonId, () => {
  selectedFamilyId.value = null
})

const drawerOpen = ref(false)
const editing = ref<SeasonFamilyGroup | null>(null)
const form = reactive<FamilyFormState>({ name: '', notes: '' })
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})

const createMutation = useCreateFamilyGroupMutation()
const updateMutation = useUpdateFamilyGroupMutation()
const deleteMutation = useDeleteFamilyGroupMutation()
const primaryMutation = useSetFamilyPrimaryMutation()
const submitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

function openCreate(): void {
  editing.value = null
  Object.assign(form, { name: '', notes: '' })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function openEdit(family: SeasonFamilyGroup): void {
  editing.value = family
  Object.assign(form, { name: family.name, notes: family.notes ?? '' })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

async function submit(): Promise<void> {
  if (!seasonWritable.value || !selectedSeasonId.value) {
    formError.value = t('seasonOps.errors.seasonClosed')
    return
  }
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const local = validateFamilyForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return

  const payload = { name: form.name.trim(), notes: form.notes.trim() || null }

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        id: editing.value.id,
        payload,
        seasonId: selectedSeasonId.value,
      })
      toast.success(t('seasonOps.families.toasts.updated'))
    } else {
      await createMutation.mutateAsync({ payload, seasonId: selectedSeasonId.value })
      toast.success(t('seasonOps.families.toasts.created'))
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value =
      error instanceof ApiError ? error.message : t('seasonOps.errors.generic')
  }
}

async function onDelete(family: SeasonFamilyGroup): Promise<void> {
  if (!seasonWritable.value || !selectedSeasonId.value) return
  const ok = await confirm({
    title: t('seasonOps.families.confirmDeleteTitle'),
    message: t('seasonOps.families.confirmDeleteBody'),
    confirmLabel: t('seasonOps.families.delete'),
    cancelLabel: t('pilgrims.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync({ id: family.id, seasonId: selectedSeasonId.value })
    if (selectedFamilyId.value === family.id) selectedFamilyId.value = null
    toast.success(t('seasonOps.families.toasts.deleted'))
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : t('seasonOps.errors.generic'))
  }
}

async function setPrimary(seasonPilgrimId: number): Promise<void> {
  if (!selectedFamilyId.value || !selectedSeasonId.value || !seasonWritable.value) return
  try {
    await primaryMutation.mutateAsync({
      familyGroupId: selectedFamilyId.value,
      seasonPilgrimId,
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('seasonOps.families.toasts.primarySet'))
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : t('seasonOps.errors.generic'))
  }
}

function fieldMessage(key: string): string {
  const code = fieldErrors[key]
  return code ? t(`seasonOps.errors.validation.${code}`) : ''
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('seasonOps.families.title')"
      :subtitle="t('seasonOps.families.subtitle')"
      :meta="
        listState === 'ready'
          ? t('seasonOps.families.total', { count: families.length })
          : undefined
      "
    >
      <template #actions>
        <PermissionGuard permission="season_family_groups.manage">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="!seasonWritable || !selectedSeasonId"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            {{ t('seasonOps.families.add') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <SeasonWorkspaceBar />

    <div
      v-if="listState === 'need_season'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('seasonOps.bar.needSeason') }}
    </div>
    <div
      v-else-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('seasonOps.families.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('seasonOps.families.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('pilgrims.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('seasonOps.families.empty') }}
    </div>
    <div v-else class="grid gap-4 lg:grid-cols-2">
      <div class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface">
        <table class="min-w-full text-sm">
          <thead class="bg-[#F4F6F5]">
            <tr>
              <th class="px-4 py-3 text-start text-xs font-bold text-brand-text-muted">
                {{ t('seasonOps.families.columns.name') }}
              </th>
              <th class="px-4 py-3 text-center text-xs font-bold text-brand-text-muted">
                {{ t('seasonOps.families.columns.count') }}
              </th>
              <th class="px-4 py-3 text-center text-xs font-bold text-brand-text-muted">
                {{ t('seasonOps.families.columns.actions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="family in families"
              :key="family.id"
              class="cursor-pointer border-t border-brand-border/80"
              :class="selectedFamilyId === family.id ? 'bg-emerald-50/50' : ''"
              @click="selectedFamilyId = family.id"
            >
              <td class="px-4 py-3 font-semibold">{{ family.name }}</td>
              <td class="px-4 py-3 text-center">{{ family.members_count ?? '—' }}</td>
              <td class="px-4 py-3" @click.stop>
                <div class="flex justify-center gap-1">
                  <PermissionGuard permission="season_family_groups.manage">
                    <button
                      type="button"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-brand-bg disabled:opacity-40"
                      :disabled="!seasonWritable"
                      @click="openEdit(family)"
                    >
                      <Pencil class="h-4 w-4" />
                    </button>
                    <button
                      type="button"
                      class="inline-flex h-8 items-center rounded-lg px-2 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:opacity-40"
                      :disabled="!seasonWritable"
                      @click="onDelete(family)"
                    >
                      {{ t('seasonOps.families.delete') }}
                    </button>
                  </PermissionGuard>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <section class="rounded-2xl border border-brand-border bg-brand-surface p-5">
        <div class="flex items-center gap-2">
          <Users class="h-4 w-4 text-brand-primary" />
          <h2 class="text-base font-bold">{{ t('seasonOps.families.membersTitle') }}</h2>
        </div>
        <p v-if="!selectedFamilyId" class="mt-4 text-sm text-brand-text-muted">
          {{ t('seasonOps.families.selectHint') }}
        </p>
        <p
          v-else-if="detailQuery.isLoading.value"
          class="mt-4 text-sm text-brand-text-muted"
        >
          {{ t('seasonOps.families.membersLoading') }}
        </p>
        <p
          v-else-if="detailQuery.isError.value"
          class="mt-4 text-sm text-red-700"
        >
          {{ t('seasonOps.families.errors.members') }}
        </p>
        <p
          v-else-if="!(detailQuery.data.value?.members?.length)"
          class="mt-4 text-sm text-brand-text-muted"
        >
          {{ t('seasonOps.families.membersEmpty') }}
          <span class="mt-1 block text-xs">{{ t('seasonOps.families.membersAssignHint') }}</span>
        </p>
        <ul v-else class="mt-4 space-y-2">
          <li
            v-for="member in detailQuery.data.value?.members"
            :key="member.season_pilgrim_id"
            class="flex items-center justify-between rounded-xl border border-brand-border px-3 py-2 text-sm"
          >
            <div>
              <p class="font-semibold">{{ member.pilgrim?.full_name ?? '—' }}</p>
              <p class="text-xs text-brand-text-muted" dir="ltr">
                {{ member.reference_number }}
                <span v-if="member.is_primary_member" class="ms-2 text-emerald-700">
                  {{ t('seasonOps.families.primary') }}
                </span>
              </p>
            </div>
            <PermissionGuard permission="season_family_groups.manage">
              <button
                v-if="!member.is_primary_member"
                type="button"
                class="text-xs font-semibold text-brand-primary-dark disabled:opacity-40"
                :disabled="!seasonWritable"
                @click="setPrimary(member.season_pilgrim_id)"
              >
                {{ t('seasonOps.families.setPrimary') }}
              </button>
            </PermissionGuard>
          </li>
        </ul>
      </section>
    </div>

    <Teleport to="body">
      <div v-if="drawerOpen" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-[rgba(15,23,20,0.32)]" @click="drawerOpen = false" />
        <aside
          class="absolute inset-y-0 start-0 flex h-dvh w-full max-w-[440px] flex-col bg-brand-surface"
          role="dialog"
          aria-modal="true"
        >
          <header class="border-b border-brand-border px-5 py-4">
            <h3 class="text-lg font-bold">
              {{
                editing
                  ? t('seasonOps.families.editTitle')
                  : t('seasonOps.families.createTitle')
              }}
            </h3>
          </header>
          <form class="flex flex-1 flex-col" @submit.prevent="submit">
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
              <p v-if="formError" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ formError }}
              </p>
              <label class="block space-y-1">
                <span class="text-sm font-semibold">{{ t('seasonOps.families.fields.name') }}</span>
                <input
                  v-model="form.name"
                  class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
                  maxlength="255"
                />
                <span v-if="fieldMessage('name')" class="text-xs text-red-600">{{
                  fieldMessage('name')
                }}</span>
              </label>
              <label class="block space-y-1">
                <span class="text-sm font-semibold">{{ t('seasonOps.families.fields.notes') }}</span>
                <textarea
                  v-model="form.notes"
                  rows="3"
                  class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
                  maxlength="5000"
                />
              </label>
            </div>
            <footer class="flex justify-end gap-2 border-t border-brand-border px-5 py-4">
              <button
                type="button"
                class="h-11 rounded-xl border border-brand-border px-4 text-sm font-semibold"
                @click="drawerOpen = false"
              >
                {{ t('pilgrims.cancel') }}
              </button>
              <button
                type="submit"
                class="h-11 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-60"
                :disabled="submitting || !seasonWritable"
              >
                {{ t('pilgrims.save') }}
              </button>
            </footer>
          </form>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
