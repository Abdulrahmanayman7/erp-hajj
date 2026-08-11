<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  ChevronLeft,
  ChevronRight,
  Pencil,
  Plus,
  Search,
  UserCheck,
  UserX,
} from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppSelect from '@/shared/components/AppSelect.vue'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import UserAvatar from '@/shared/components/UserAvatar.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { useRolesQuery } from '@/modules/roles/queries/useRolesQuery'

import UserFormDrawer from '../components/UserFormDrawer.vue'
import {
  useCreateUserMutation,
  useDisableUserMutation,
  useEnableUserMutation,
  useSyncUserRolesMutation,
  useUpdateUserMutation,
} from '../mutations/useUserMutations'
import { useUsersQuery } from '../queries/useUsersQuery'
import type { AvatarGroup, TenantUser } from '../types/users'

const { t } = useI18n()
const { can } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()

const filters = reactive({
  search: '',
  status: '' as '' | 'active' | 'disabled',
  role_id: '' as number | '',
  page: 1,
  per_page: 15,
  sort: 'name',
  direction: 'asc',
})

const queryParams = computed(() => ({ ...filters }))
const { data, isLoading, isError, refetch, isFetching } = useUsersQuery(queryParams)
const { data: rolesData } = useRolesQuery({ per_page: 100 })

const createMutation = useCreateUserMutation()
const updateMutation = useUpdateUserMutation()
const disableMutation = useDisableUserMutation()
const enableMutation = useEnableUserMutation()
const syncRolesMutation = useSyncUserRolesMutation()

const drawerOpen = ref(false)
const editing = ref<TenantUser | null>(null)
const formError = ref('')
const form = reactive({
  name: '',
  email: '',
  role_ids: [] as number[],
  send_invite: true,
  temporary_password: '',
  temporary_password_confirmation: '',
  avatar_group: 'neutral' as AvatarGroup,
})

const users = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const roles = computed(() => rolesData.value?.data ?? [])

const statusOptions = computed(() => [
  { value: '', label: t('users.filters.allStatuses') },
  { value: 'active', label: t('users.status.active') },
  { value: 'disabled', label: t('users.status.disabled') },
])

const roleOptions = computed(() => [
  { value: '', label: t('users.filters.allRoles') },
  ...roles.value.map((role) => ({ value: role.id, label: role.name })),
])

const isSubmitting = computed(
  () =>
    createMutation.isPending.value ||
    updateMutation.isPending.value ||
    syncRolesMutation.isPending.value,
)

watch(
  () => [filters.search, filters.status, filters.role_id],
  () => {
    filters.page = 1
  },
)

function openCreate(): void {
  editing.value = null
  form.name = ''
  form.email = ''
  form.role_ids = []
  form.send_invite = true
  form.temporary_password = ''
  form.temporary_password_confirmation = ''
  form.avatar_group = 'neutral'
  formError.value = ''
  drawerOpen.value = true
}

function openEdit(user: TenantUser): void {
  editing.value = user
  form.name = user.name
  form.email = user.email
  form.role_ids = user.roles.map((role) => role.id)
  form.avatar_group = user.avatar_group ?? 'neutral'
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
        payload: {
          name: form.name,
          email: form.email,
          avatar_group: form.avatar_group,
        },
      })
      if (can('users.assign_roles')) {
        await syncRolesMutation.mutateAsync({
          id: editing.value.id,
          roleIds: form.role_ids,
        })
      }
      closeDrawer()
      toast.success(t('users.successUpdate'))
      return
    }

    const sendInvite = form.send_invite
    await createMutation.mutateAsync({
      name: form.name,
      email: form.email,
      role_ids: form.role_ids,
      send_invite: sendInvite,
      temporary_password: sendInvite ? undefined : form.temporary_password,
      temporary_password_confirmation: sendInvite
        ? undefined
        : form.temporary_password_confirmation,
      avatar_group: form.avatar_group,
    })
    closeDrawer()
    if (!sendInvite) {
      toast.success(t('users.successCreate'))
    } else if (import.meta.env.DEV) {
      toast.info(t('users.successCreateInviteLocal'), 6500)
    } else {
      toast.success(t('users.successCreateInvite'))
    }
  } catch (error) {
    formError.value =
      error instanceof ApiError
        ? error.message || t('users.errors.generic')
        : t('users.errors.generic')
  }
}

async function toggleStatus(user: TenantUser): Promise<void> {
  const disabling = user.status === 'active'
  const confirmed = await confirm({
    title: disabling ? t('users.confirmDisableTitle') : t('users.confirmEnableTitle'),
    message: disabling ? t('users.confirmDisableBody') : t('users.confirmEnableBody'),
    confirmLabel: disabling ? t('users.confirmDisableCta') : t('users.confirmEnableCta'),
    cancelLabel: t('users.cancel'),
    variant: disabling ? 'warning' : 'primary',
  })
  if (!confirmed) {
    return
  }
  try {
    if (disabling) {
      await disableMutation.mutateAsync(user.id)
      toast.success(t('users.successDisable'))
    } else {
      await enableMutation.mutateAsync(user.id)
      toast.success(t('users.successEnable'))
    }
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : t('users.errors.generic'))
  }
}

function formatDate(value: string | null): string {
  if (!value) {
    return '—'
  }
  return new Date(value).toLocaleDateString('ar-SA')
}

</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div class="min-w-0">
        <h2 class="text-[1.75rem] font-bold leading-tight text-brand-text">
          {{ t('users.title') }}
        </h2>
        <p v-if="meta" class="mt-1.5 text-sm text-brand-text-secondary">
          <span
            class="inline-flex items-center rounded-full bg-brand-primary-soft px-2.5 py-0.5 text-xs font-semibold text-brand-primary-dark"
          >
            {{ t('users.total', { count: meta.total }) }}
          </span>
        </p>
      </div>
      <PermissionGuard permission="users.create">
        <button
          type="button"
          class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" :stroke-width="2.25" />
          <span>{{ t('users.add') }}</span>
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
          :placeholder="t('users.searchPlaceholder')"
        />
      </div>
      <AppSelect v-model="filters.status" :options="statusOptions" />
      <AppSelect v-model="filters.role_id" :options="roleOptions" />
    </div>

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('users.loading') }}
    </div>
    <div v-else-if="isError" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center">
      <p class="text-sm text-red-700">{{ t('users.errors.load') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('users.retry') }}
      </button>
    </div>
    <div
      v-else-if="users.length === 0"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('users.empty') }}
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
                {{ t('users.columns.name') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('users.columns.email') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('users.columns.roles') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('users.columns.status') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('users.columns.created') }}
              </th>
              <th
                class="w-28 whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('users.columns.actions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(user, index) in users"
              :key="user.id"
              class="group transition-colors duration-150"
              :class="index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'"
            >
              <td class="border-b border-brand-border/80 px-5 py-3.5 group-hover:bg-[#EEF2F0]">
                <div class="flex min-w-0 items-center gap-2.5">
                  <UserAvatar :user="user" size="md" />
                  <span class="truncate font-semibold text-brand-text">{{ user.name }}</span>
                </div>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
                dir="ltr"
              >
                {{ user.email }}
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center group-hover:bg-[#EEF2F0]">
                <div class="flex flex-wrap items-center justify-center gap-1.5">
                  <span
                    v-for="role in user.roles"
                    :key="role.id"
                    class="inline-flex items-center rounded-lg bg-brand-primary-soft px-2 py-0.5 text-xs font-semibold text-brand-primary-dark ring-1 ring-brand-primary/10"
                  >
                    {{ role.name }}
                  </span>
                  <span v-if="user.roles.length === 0" class="text-brand-text-muted">—</span>
                </div>
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center group-hover:bg-[#EEF2F0]">
                <div class="flex justify-center">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="
                      user.status === 'active'
                        ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/70'
                        : 'bg-neutral-100 text-neutral-600 ring-1 ring-neutral-200/80'
                    "
                  >
                    <span
                      class="h-1.5 w-1.5 rounded-full"
                      :class="user.status === 'active' ? 'bg-emerald-500' : 'bg-neutral-400'"
                    />
                    {{ t(`users.status.${user.status}`) }}
                  </span>
                </div>
              </td>
              <td
                class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
              >
                {{ formatDate(user.created_at) }}
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center group-hover:bg-[#EEF2F0]">
                <div class="inline-flex items-center justify-center gap-1">
                  <PermissionGuard permission="users.update">
                    <AppTooltip :text="t('users.actions.edit')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-primary-dark transition hover:bg-brand-primary-soft"
                        :aria-label="t('users.actions.edit')"
                        @click="openEdit(user)"
                      >
                        <Pencil class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>
                  </PermissionGuard>
                  <PermissionGuard permission="users.disable">
                    <AppTooltip
                      v-if="user.status === 'active'"
                      :text="t('users.actions.disable')"
                    >
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-amber-700 transition hover:bg-amber-50"
                        :aria-label="t('users.actions.disable')"
                        @click="toggleStatus(user)"
                      >
                        <UserX class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>
                    <AppTooltip v-else :text="t('users.actions.enable')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-emerald-700 transition hover:bg-emerald-50"
                        :aria-label="t('users.actions.enable')"
                        @click="toggleStatus(user)"
                      >
                        <UserCheck class="h-4 w-4" :stroke-width="2" />
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
          <span>{{ t('users.prev') }}</span>
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
          <span>{{ t('users.next') }}</span>
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
        </button>
      </div>
    </div>

    <UserFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :roles="roles"
      :form-error="formError"
      :submitting="isSubmitting"
      :can-assign-roles="can('users.assign_roles')"
      @close="closeDrawer"
      @submit="submitForm"
    />
  </div>
</template>
