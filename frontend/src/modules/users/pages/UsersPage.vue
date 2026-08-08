<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useRolesQuery } from '@/modules/roles/queries/useRolesQuery'

import {
  useCreateUserMutation,
  useDisableUserMutation,
  useEnableUserMutation,
  useSyncUserRolesMutation,
  useUpdateUserMutation,
} from '../mutations/useUserMutations'
import { useUsersQuery } from '../queries/useUsersQuery'
import type { TenantUser } from '../types/users'

const { t } = useI18n()
const { can } = usePermissions()

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
})

const users = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const roles = computed(() => rolesData.value?.data ?? [])

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
  formError.value = ''
  drawerOpen.value = true
}

function openEdit(user: TenantUser): void {
  editing.value = user
  form.name = user.name
  form.email = user.email
  form.role_ids = user.roles.map((role) => role.id)
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
        payload: { name: form.name, email: form.email },
      })
      if (can('users.assign_roles')) {
        await syncRolesMutation.mutateAsync({
          id: editing.value.id,
          roleIds: form.role_ids,
        })
      }
    } else {
      await createMutation.mutateAsync({
        name: form.name,
        email: form.email,
        role_ids: form.role_ids,
        send_invite: form.send_invite,
        temporary_password: form.send_invite ? undefined : form.temporary_password,
        temporary_password_confirmation: form.send_invite
          ? undefined
          : form.temporary_password_confirmation,
      })
    }
    closeDrawer()
  } catch (error) {
    formError.value =
      error instanceof ApiError
        ? error.message || t('users.errors.generic')
        : t('users.errors.generic')
  }
}

async function toggleStatus(user: TenantUser): Promise<void> {
  const confirmMessage =
    user.status === 'active' ? t('users.confirmDisable') : t('users.confirmEnable')
  if (!window.confirm(confirmMessage)) {
    return
  }
  try {
    if (user.status === 'active') {
      await disableMutation.mutateAsync(user.id)
    } else {
      await enableMutation.mutateAsync(user.id)
    }
  } catch (error) {
    window.alert(
      error instanceof ApiError ? error.message : t('users.errors.generic'),
    )
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
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-2xl font-semibold text-neutral-900">{{ t('users.title') }}</h2>
        <p v-if="meta" class="mt-1 text-sm text-neutral-500">
          {{ t('users.total', { count: meta.total }) }}
        </p>
      </div>
      <PermissionGuard permission="users.create">
        <button
          type="button"
          class="rounded-md bg-emerald-800 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
          @click="openCreate"
        >
          {{ t('users.add') }}
        </button>
      </PermissionGuard>
    </div>

    <div class="flex flex-wrap gap-3 rounded-xl border border-neutral-200 bg-white p-4">
      <input
        v-model="filters.search"
        type="search"
        class="min-w-48 flex-1 rounded-md border border-neutral-300 px-3 py-2 text-sm"
        :placeholder="t('users.searchPlaceholder')"
      />
      <select v-model="filters.status" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
        <option value="">{{ t('users.filters.allStatuses') }}</option>
        <option value="active">{{ t('users.status.active') }}</option>
        <option value="disabled">{{ t('users.status.disabled') }}</option>
      </select>
      <select v-model="filters.role_id" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
        <option value="">{{ t('users.filters.allRoles') }}</option>
        <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
      </select>
    </div>

    <div v-if="isLoading" class="rounded-xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500">
      {{ t('users.loading') }}
    </div>
    <div v-else-if="isError" class="rounded-xl border border-red-200 bg-red-50 p-8 text-center">
      <p class="text-sm text-red-700">{{ t('users.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm text-emerald-800 underline" @click="() => refetch()">
        {{ t('users.retry') }}
      </button>
    </div>
    <div v-else-if="users.length === 0" class="rounded-xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500">
      {{ t('users.empty') }}
    </div>
    <div v-else class="overflow-x-auto rounded-xl border border-neutral-200 bg-white">
      <table class="min-w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-600">
          <tr>
            <th class="px-4 py-3 text-start font-medium">{{ t('users.columns.name') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('users.columns.email') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('users.columns.roles') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('users.columns.status') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('users.columns.created') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('users.columns.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" class="border-t border-neutral-100">
            <td class="px-4 py-3">{{ user.name }}</td>
            <td class="px-4 py-3">{{ user.email }}</td>
            <td class="px-4 py-3">
              <span
                v-for="role in user.roles"
                :key="role.id"
                class="me-1 inline-block rounded bg-emerald-50 px-2 py-0.5 text-xs text-emerald-900"
              >
                {{ role.name }}
              </span>
              <span v-if="user.roles.length === 0">—</span>
            </td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2 py-0.5 text-xs"
                :class="user.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-neutral-200 text-neutral-700'"
              >
                {{ t(`users.status.${user.status}`) }}
              </span>
            </td>
            <td class="px-4 py-3">{{ formatDate(user.created_at) }}</td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-2">
                <PermissionGuard permission="users.update">
                  <button type="button" class="text-emerald-800 hover:underline" @click="openEdit(user)">
                    {{ t('users.actions.edit') }}
                  </button>
                </PermissionGuard>
                <PermissionGuard permission="users.disable">
                  <button type="button" class="text-neutral-700 hover:underline" @click="toggleStatus(user)">
                    {{ user.status === 'active' ? t('users.actions.disable') : t('users.actions.enable') }}
                  </button>
                </PermissionGuard>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t border-neutral-100 px-4 py-3 text-sm">
        <button
          type="button"
          class="disabled:opacity-40"
          :disabled="filters.page <= 1 || isFetching"
          @click="filters.page -= 1"
        >
          {{ t('users.prev') }}
        </button>
        <span>{{ filters.page }} / {{ meta.last_page }}</span>
        <button
          type="button"
          class="disabled:opacity-40"
          :disabled="filters.page >= meta.last_page || isFetching"
          @click="filters.page += 1"
        >
          {{ t('users.next') }}
        </button>
      </div>
    </div>

    <div
      v-if="drawerOpen"
      class="fixed inset-0 z-40 flex justify-start bg-black/30"
      @click.self="closeDrawer"
    >
      <aside class="flex h-full w-full max-w-md flex-col bg-white shadow-xl">
        <header class="border-b border-neutral-200 px-5 py-4">
          <h3 class="text-lg font-semibold">
            {{ editing ? t('users.editTitle') : t('users.createTitle') }}
          </h3>
        </header>
        <form class="flex flex-1 flex-col gap-4 overflow-y-auto p-5" @submit.prevent="submitForm">
          <label class="block text-sm">
            <span class="mb-1 block text-neutral-700">{{ t('users.fields.name') }}</span>
            <input v-model="form.name" required class="w-full rounded-md border border-neutral-300 px-3 py-2" />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-neutral-700">{{ t('users.fields.email') }}</span>
            <input v-model="form.email" type="email" required class="w-full rounded-md border border-neutral-300 px-3 py-2" />
          </label>
          <PermissionGuard permission="users.assign_roles">
            <fieldset class="text-sm">
              <legend class="mb-2 text-neutral-700">{{ t('users.fields.roles') }}</legend>
              <label v-for="role in roles" :key="role.id" class="mb-1 flex items-center gap-2">
                <input v-model="form.role_ids" type="checkbox" :value="role.id" :disabled="!role.is_active && !form.role_ids.includes(role.id)" />
                <span>{{ role.name }}</span>
              </label>
            </fieldset>
          </PermissionGuard>
          <label v-if="!editing" class="flex items-center gap-2 text-sm">
            <input v-model="form.send_invite" type="checkbox" />
            <span>{{ t('users.fields.sendInvite') }}</span>
          </label>
          <template v-if="!editing && !form.send_invite">
            <label class="block text-sm">
              <span class="mb-1 block text-neutral-700">{{ t('users.fields.temporaryPassword') }}</span>
              <input v-model="form.temporary_password" type="password" class="w-full rounded-md border border-neutral-300 px-3 py-2" />
            </label>
            <label class="block text-sm">
              <span class="mb-1 block text-neutral-700">{{ t('users.fields.confirmPassword') }}</span>
              <input v-model="form.temporary_password_confirmation" type="password" class="w-full rounded-md border border-neutral-300 px-3 py-2" />
            </label>
          </template>
          <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
          <div class="mt-auto flex gap-2 pt-4">
            <button
              type="submit"
              class="rounded-md bg-emerald-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
              :disabled="createMutation.isPending.value || updateMutation.isPending.value || syncRolesMutation.isPending.value"
            >
              {{ t('users.save') }}
            </button>
            <button type="button" class="rounded-md border border-neutral-300 px-4 py-2 text-sm" @click="closeDrawer">
              {{ t('users.cancel') }}
            </button>
          </div>
        </form>
      </aside>
    </div>
  </div>
</template>
