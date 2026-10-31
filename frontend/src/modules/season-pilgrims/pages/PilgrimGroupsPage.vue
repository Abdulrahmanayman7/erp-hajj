<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { Pencil, Plus } from 'lucide-vue-next'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useToast } from '@/shared/composables/useToast'

import SeasonWorkspaceBar from '../components/SeasonWorkspaceBar.vue'
import {
  useCreatePilgrimGroupMutation,
  useDeletePilgrimGroupMutation,
  useUpdatePilgrimGroupMutation,
} from '../mutations/useSeasonOpsMutations'
import { usePilgrimGroupsQuery } from '../queries/useSeasonOpsQueries'
import { useSeasonsQuery } from '../queries/useSeasonsQuery'
import type { PilgrimGroupFormState, SeasonPilgrimGroup } from '../types/pilgrimGroups'
import {
  resolveSeasonScopedListState,
  validatePilgrimGroupForm,
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

const { data, isLoading, isError, refetch } = usePilgrimGroupsQuery(
  selectedSeasonId,
  () => selectedSeasonId.value != null,
)
const groups = computed(() => data.value ?? [])
const listState = computed(() =>
  resolveSeasonScopedListState({
    hasSeason: selectedSeasonId.value != null,
    isLoading: isLoading.value,
    isError: isError.value,
    count: groups.value.length,
  }),
)

const drawerOpen = ref(false)
const editing = ref<SeasonPilgrimGroup | null>(null)
const form = reactive<PilgrimGroupFormState>({ code: '', name: '', notes: '' })
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})

const createMutation = useCreatePilgrimGroupMutation()
const updateMutation = useUpdatePilgrimGroupMutation()
const deleteMutation = useDeletePilgrimGroupMutation()
const submitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

function openCreate(): void {
  editing.value = null
  Object.assign(form, { code: '', name: '', notes: '' })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function openEdit(group: SeasonPilgrimGroup): void {
  editing.value = group
  Object.assign(form, { code: group.code, name: group.name, notes: group.notes ?? '' })
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
  const local = validatePilgrimGroupForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return

  const payload = {
    code: form.code.trim(),
    name: form.name.trim(),
    notes: form.notes.trim() || null,
  }

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        id: editing.value.id,
        payload,
        seasonId: selectedSeasonId.value,
      })
      toast.success(t('seasonOps.groups.toasts.updated'))
    } else {
      await createMutation.mutateAsync({ payload, seasonId: selectedSeasonId.value })
      toast.success(t('seasonOps.groups.toasts.created'))
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value =
      error instanceof ApiError ? error.message : t('seasonOps.errors.generic')
  }
}

async function onDelete(group: SeasonPilgrimGroup): Promise<void> {
  if (!seasonWritable.value || !selectedSeasonId.value) return
  const ok = await confirm({
    title: t('seasonOps.groups.confirmDeleteTitle'),
    message: t('seasonOps.groups.confirmDeleteBody'),
    confirmLabel: t('seasonOps.groups.delete'),
    cancelLabel: t('pilgrims.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync({ id: group.id, seasonId: selectedSeasonId.value })
    toast.success(t('seasonOps.groups.toasts.deleted'))
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
      :title="t('seasonOps.groups.title')"
      :subtitle="t('seasonOps.groups.subtitle')"
      :meta="
        listState === 'ready' ? t('seasonOps.groups.total', { count: groups.length }) : undefined
      "
    >
      <template #actions>
        <PermissionGuard permission="season_pilgrim_groups.manage">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="!seasonWritable || !selectedSeasonId"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            {{ t('seasonOps.groups.add') }}
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
      {{ t('seasonOps.groups.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('seasonOps.groups.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('pilgrims.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('seasonOps.groups.empty') }}
    </div>
    <div
      v-else
      class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface"
    >
      <table class="min-w-full text-sm">
        <thead class="bg-[#F4F6F5]">
          <tr>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('seasonOps.groups.columns.code') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('seasonOps.groups.columns.name') }}
            </th>
            <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
              {{ t('seasonOps.groups.columns.count') }}
            </th>
            <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
              {{ t('seasonOps.groups.columns.actions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="group in groups"
            :key="group.id"
            class="border-t border-brand-border/80"
          >
            <td class="px-5 py-3.5 font-mono text-xs" dir="ltr">{{ group.code }}</td>
            <td class="px-5 py-3.5 font-semibold">{{ group.name }}</td>
            <td class="px-5 py-3.5 text-center">{{ group.season_pilgrims_count ?? '—' }}</td>
            <td class="px-5 py-3.5">
              <div class="flex justify-center gap-1">
                <PermissionGuard permission="season_pilgrim_groups.manage">
                  <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-brand-bg disabled:opacity-40"
                    :disabled="!seasonWritable"
                    @click="openEdit(group)"
                  >
                    <Pencil class="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:opacity-40"
                    :disabled="!seasonWritable"
                    @click="onDelete(group)"
                  >
                    {{ t('seasonOps.groups.delete') }}
                  </button>
                </PermissionGuard>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
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
                editing ? t('seasonOps.groups.editTitle') : t('seasonOps.groups.createTitle')
              }}
            </h3>
          </header>
          <form class="flex flex-1 flex-col" @submit.prevent="submit">
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
              <p v-if="formError" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ formError }}
              </p>
              <label class="block space-y-1">
                <span class="text-sm font-semibold">{{ t('seasonOps.groups.fields.code') }}</span>
                <input
                  v-model="form.code"
                  class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
                  dir="ltr"
                  maxlength="40"
                />
                <span v-if="fieldMessage('code')" class="text-xs text-red-600">{{
                  fieldMessage('code')
                }}</span>
              </label>
              <label class="block space-y-1">
                <span class="text-sm font-semibold">{{ t('seasonOps.groups.fields.name') }}</span>
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
                <span class="text-sm font-semibold">{{ t('seasonOps.groups.fields.notes') }}</span>
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
