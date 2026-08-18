<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import {
  ChevronLeft,
  ChevronRight,
  KeyRound,
  Pencil,
  Plus,
  Power,
  PowerOff,
  Search,
  Trash2,
} from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useToast } from '@/shared/composables/useToast'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

import RoleFormDrawer from '../components/RoleFormDrawer.vue'
import {
  useActivateRoleMutation,
  useCreateRoleMutation,
  useDeactivateRoleMutation,
  useDeleteRoleMutation,
  useUpdateRoleMutation,
} from '../mutations/useRoleMutations'
import { useRolesQuery } from '../queries/useRolesQuery'
import type { RoleSummary } from '../types/roles'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()

const filters = reactive({
  search: '',
  page: 1,
  per_page: 20,
})

const committedSearch = useDebouncedRef(() => filters.search)
const queryParams = computed(() => ({ ...filters, search: committedSearch.value }))
const { data, isLoading, isError, refetch, isFetching } = useRolesQuery(queryParams)
const createMutation = useCreateRoleMutation()
const updateMutation = useUpdateRoleMutation()
const activateMutation = useActivateRoleMutation()
const deactivateMutation = useDeactivateRoleMutation()
const deleteMutation = useDeleteRoleMutation()

const drawerOpen = ref(false)
const editing = ref<RoleSummary | null>(null)
const formError = ref('')
const form = reactive({
  name: '',
  code: '',
  description: '',
})

const roles = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)

const isSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

watch(
  committedSearch,
  () => {
    filters.page = 1
  },
)

function openCreate(): void {
  editing.value = null
  form.name = ''
  form.code = ''
  form.description = ''
  formError.value = ''
  drawerOpen.value = true
}

function openEdit(role: RoleSummary): void {
  editing.value = role
  form.name = role.name
  form.code = role.code
  form.description = role.description ?? ''
  formError.value = ''
  drawerOpen.value = true
}

function closeDrawer(): void {
  drawerOpen.value = false
}

async function submitForm(): Promise<void> {
  formError.value = ''
  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        id: editing.value.id,
        payload: { name: form.name, description: form.description || null },
      })
      closeDrawer()
      toast.success(t('roles.successUpdate'))
      return
    }

    await createMutation.mutateAsync({
      name: form.name,
      code: form.code || undefined,
      description: form.description || undefined,
    })
    closeDrawer()
    toast.success(t('roles.successCreate'))
  } catch (error) {
    formError.value = error instanceof ApiError ? error.message : t('roles.errors.generic')
  }
}

async function toggleActive(role: RoleSummary): Promise<void> {
  if (role.code === 'tenant_owner') {
    return
  }

  const deactivating = role.is_active
  const confirmed = await confirm({
    title: deactivating ? t('roles.confirmDeactivateTitle') : t('roles.confirmActivateTitle'),
    message: deactivating ? t('roles.confirmDeactivateBody') : t('roles.confirmActivateBody'),
    confirmLabel: deactivating ? t('roles.confirmDeactivateCta') : t('roles.confirmActivateCta'),
    cancelLabel: t('roles.cancel'),
    variant: deactivating ? 'warning' : 'primary',
  })
  if (!confirmed) {
    return
  }

  try {
    if (deactivating) {
      await deactivateMutation.mutateAsync(role.id)
      toast.success(t('roles.successDeactivate'))
    } else {
      await activateMutation.mutateAsync(role.id)
      toast.success(t('roles.successActivate'))
    }
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : t('roles.errors.generic'))
  }
}

async function removeRole(role: RoleSummary): Promise<void> {
  if (role.is_system) {
    return
  }

  const confirmed = await confirm({
    title: t('roles.confirmDeleteTitle'),
    message: t('roles.confirmDeleteBody'),
    confirmLabel: t('roles.confirmDeleteCta'),
    cancelLabel: t('roles.cancel'),
    variant: 'danger',
  })
  if (!confirmed) {
    return
  }

  try {
    await deleteMutation.mutateAsync(role.id)
    toast.success(t('roles.successDelete'))
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : t('roles.errors.generic'))
  }
}

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0]!.slice(0, 2)
  return `${parts[0]!.slice(0, 1)}${parts[1]!.slice(0, 1)}`
}

</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div class="min-w-0">
        <h2 class="text-[1.75rem] font-bold leading-tight text-brand-text">
          {{ t('roles.title') }}
        </h2>
        <p class="mt-1.5 text-sm text-brand-text-secondary">
          <span
            v-if="meta"
            class="inline-flex items-center rounded-full bg-brand-primary-soft px-2.5 py-0.5 text-xs font-semibold text-brand-primary-dark"
          >
            {{ t('roles.total', { count: meta.total }) }}
          </span>
          <span v-else>{{ t('roles.subtitle') }}</span>
        </p>
      </div>
      <PermissionGuard permission="roles.create">
        <button
          type="button"
          class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" :stroke-width="2.25" />
          <span>{{ t('roles.add') }}</span>
        </button>
      </PermissionGuard>
    </div>

    <div
      class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
    >
      <div class="relative min-w-48 flex-1">
        <Search
          class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
          :stroke-width="1.75"
          aria-hidden="true"
        />
        <input
          v-model="filters.search"
          type="search"
          class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
          :placeholder="t('roles.searchPlaceholder')"
        />
      </div>
    </div>

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('roles.loading') }}
    </div>
    <div v-else-if="isError" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center">
      <p class="text-sm text-red-700">{{ t('roles.errors.load') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('roles.retry') }}
      </button>
    </div>
    <div
      v-else-if="roles.length === 0"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('roles.empty') }}
    </div>
    <div
      v-else
      class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
    >
      <div class="overflow-x-auto">
        <table class="min-w-full border-separate border-spacing-0 text-sm">
          <thead>
            <tr class="bg-[#F4F6F5]">
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('roles.columns.name') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('roles.columns.type') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('roles.columns.status') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('roles.columns.members') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('roles.columns.permissions') }}
              </th>
              <th
                class="w-40 whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('roles.columns.actions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(role, index) in roles"
              :key="role.id"
              class="group transition-colors duration-150"
              :class="index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'"
            >
              <td class="border-b border-brand-border/80 px-5 py-3.5 group-hover:bg-[#EEF2F0]">
                <div class="flex min-w-0 items-center gap-3">
                  <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-primary-soft text-xs font-bold text-brand-primary-dark"
                  >
                    {{ initials(role.name) }}
                  </span>
                  <span class="min-w-0">
                    <span class="block truncate font-semibold text-brand-text">{{ role.name }}</span>
                    <span
                      v-if="role.description"
                      class="mt-0.5 block truncate text-xs text-brand-text-muted"
                    >
                      {{ role.description }}
                    </span>
                  </span>
                </div>
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center group-hover:bg-[#EEF2F0]">
                <div class="flex justify-center">
                  <span
                    class="inline-flex items-center rounded-lg px-2 py-0.5 text-xs font-semibold ring-1"
                    :class="
                      role.is_system
                        ? 'bg-brand-gold-soft text-[#8A6A2E] ring-brand-gold/35'
                        : 'bg-neutral-100 text-neutral-700 ring-neutral-200/80'
                    "
                  >
                    {{ role.is_system ? t('roles.system') : t('roles.custom') }}
                  </span>
                </div>
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center group-hover:bg-[#EEF2F0]">
                <div class="flex justify-center">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="
                      role.is_active
                        ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/70'
                        : 'bg-neutral-100 text-neutral-600 ring-1 ring-neutral-200/80'
                    "
                  >
                    <span
                      class="h-1.5 w-1.5 rounded-full"
                      :class="role.is_active ? 'bg-emerald-500' : 'bg-neutral-400'"
                    />
                    {{ role.is_active ? t('roles.active') : t('roles.inactive') }}
                  </span>
                </div>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
              >
                {{ role.users_count }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
              >
                {{ role.permissions_count }}
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center group-hover:bg-[#EEF2F0]">
                <div class="inline-flex items-center justify-center gap-1">
                  <PermissionGuard permission="roles.update">
                    <AppTooltip :text="t('roles.actions.edit')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-primary-dark transition hover:bg-brand-primary-soft"
                        :aria-label="t('roles.actions.edit')"
                        @click="openEdit(role)"
                      >
                        <Pencil class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>
                  </PermissionGuard>

                  <AppTooltip :text="t('roles.actions.permissions')">
                    <RouterLink
                      :to="`/app/roles/${role.id}/permissions`"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-primary-dark transition hover:bg-brand-primary-soft"
                      :aria-label="t('roles.actions.permissions')"
                    >
                      <KeyRound class="h-4 w-4" :stroke-width="2" />
                    </RouterLink>
                  </AppTooltip>

                  <PermissionGuard permission="roles.update">
                    <AppTooltip
                      :text="
                        role.is_active ? t('roles.actions.deactivate') : t('roles.actions.activate')
                      "
                    >
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg transition disabled:cursor-not-allowed disabled:opacity-40"
                        :class="
                          role.is_active
                            ? 'text-amber-700 hover:bg-amber-50'
                            : 'text-emerald-700 hover:bg-emerald-50'
                        "
                        :disabled="role.code === 'tenant_owner'"
                        :aria-label="
                          role.is_active
                            ? t('roles.actions.deactivate')
                            : t('roles.actions.activate')
                        "
                        @click="toggleActive(role)"
                      >
                        <PowerOff
                          v-if="role.is_active"
                          class="h-4 w-4"
                          :stroke-width="2"
                        />
                        <Power v-else class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>
                  </PermissionGuard>

                  <PermissionGuard permission="roles.delete">
                    <AppTooltip
                      :text="
                        role.is_system ? t('roles.systemProtected') : t('roles.actions.delete')
                      "
                    >
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="role.is_system"
                        :aria-label="t('roles.actions.delete')"
                        @click="removeRole(role)"
                      >
                        <Trash2 class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>
                  </PermissionGuard>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3 border-t border-brand-border bg-[#F7F8F6] px-5 py-3 text-sm"
      >
        <button
          type="button"
          class="inline-flex h-9 items-center gap-1 rounded-lg border border-brand-border bg-brand-surface px-3 font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page <= 1 || isFetching"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" :stroke-width="2" />
          <span>{{ t('roles.prev') }}</span>
        </button>
        <span class="text-xs font-semibold text-brand-text-muted">
          {{ filters.page }} / {{ meta.last_page }}
        </span>
        <button
          type="button"
          class="inline-flex h-9 items-center gap-1 rounded-lg border border-brand-border bg-brand-surface px-3 font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page >= meta.last_page || isFetching"
          @click="filters.page += 1"
        >
          <span>{{ t('roles.next') }}</span>
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
        </button>
      </div>
    </div>

    <RoleFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="formError"
      :submitting="isSubmitting"
      @close="closeDrawer"
      @submit="submitForm"
    />
  </div>
</template>
