<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'

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

const filters = reactive({
  search: '',
  page: 1,
  per_page: 20,
})

const { data, isLoading, isError, refetch } = useRolesQuery(filters)
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

async function submitForm(): Promise<void> {
  formError.value = ''
  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        id: editing.value.id,
        payload: { name: form.name, description: form.description || null },
      })
    } else {
      await createMutation.mutateAsync({
        name: form.name,
        code: form.code || undefined,
        description: form.description || undefined,
      })
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value = error instanceof ApiError ? error.message : t('roles.errors.generic')
  }
}

async function toggleActive(role: RoleSummary): Promise<void> {
  try {
    if (role.is_active) {
      await deactivateMutation.mutateAsync(role.id)
    } else {
      await activateMutation.mutateAsync(role.id)
    }
  } catch (error) {
    window.alert(error instanceof ApiError ? error.message : t('roles.errors.generic'))
  }
}

async function removeRole(role: RoleSummary): Promise<void> {
  if (role.is_system) {
    return
  }
  if (!window.confirm(t('roles.confirmDelete'))) {
    return
  }
  try {
    await deleteMutation.mutateAsync(role.id)
  } catch (error) {
    window.alert(error instanceof ApiError ? error.message : t('roles.errors.generic'))
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-2xl font-semibold text-neutral-900">{{ t('roles.title') }}</h2>
        <p class="mt-1 text-sm text-neutral-500">{{ t('roles.subtitle') }}</p>
      </div>
      <PermissionGuard permission="roles.create">
        <button
          type="button"
          class="rounded-md bg-emerald-800 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
          @click="openCreate"
        >
          {{ t('roles.add') }}
        </button>
      </PermissionGuard>
    </div>

    <input
      v-model="filters.search"
      type="search"
      class="w-full max-w-md rounded-md border border-neutral-300 px-3 py-2 text-sm"
      :placeholder="t('roles.searchPlaceholder')"
    />

    <div v-if="isLoading" class="rounded-xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500">
      {{ t('roles.loading') }}
    </div>
    <div v-else-if="isError" class="rounded-xl border border-red-200 bg-red-50 p-8 text-center">
      <p class="text-sm text-red-700">{{ t('roles.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm text-emerald-800 underline" @click="() => refetch()">
        {{ t('roles.retry') }}
      </button>
    </div>
    <div v-else-if="roles.length === 0" class="rounded-xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500">
      {{ t('roles.empty') }}
    </div>
    <div v-else class="overflow-x-auto rounded-xl border border-neutral-200 bg-white">
      <table class="min-w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-600">
          <tr>
            <th class="px-4 py-3 text-start font-medium">{{ t('roles.columns.name') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('roles.columns.code') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('roles.columns.type') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('roles.columns.status') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('roles.columns.members') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('roles.columns.permissions') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('roles.columns.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="role in roles" :key="role.id" class="border-t border-neutral-100">
            <td class="px-4 py-3">{{ role.name }}</td>
            <td class="px-4 py-3 font-mono text-xs">{{ role.code }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-0.5 text-xs" :class="role.is_system ? 'bg-amber-100 text-amber-900' : 'bg-neutral-100 text-neutral-700'">
                {{ role.is_system ? t('roles.system') : t('roles.custom') }}
              </span>
            </td>
            <td class="px-4 py-3">
              {{ role.is_active ? t('roles.active') : t('roles.inactive') }}
            </td>
            <td class="px-4 py-3">{{ role.users_count }}</td>
            <td class="px-4 py-3">{{ role.permissions_count }}</td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-2">
                <PermissionGuard permission="roles.update">
                  <button type="button" class="text-emerald-800 hover:underline" @click="openEdit(role)">
                    {{ t('roles.actions.edit') }}
                  </button>
                </PermissionGuard>
                <RouterLink
                  :to="`/app/roles/${role.id}/permissions`"
                  class="text-emerald-800 hover:underline"
                >
                  {{ t('roles.actions.permissions') }}
                </RouterLink>
                <PermissionGuard permission="roles.update">
                  <button
                    type="button"
                    class="text-neutral-700 hover:underline disabled:opacity-40"
                    :disabled="role.code === 'tenant_owner'"
                    @click="toggleActive(role)"
                  >
                    {{ role.is_active ? t('roles.actions.deactivate') : t('roles.actions.activate') }}
                  </button>
                </PermissionGuard>
                <PermissionGuard permission="roles.delete">
                  <button
                    type="button"
                    class="text-red-700 hover:underline disabled:opacity-40"
                    :disabled="role.is_system"
                    :title="role.is_system ? t('roles.systemProtected') : undefined"
                    @click="removeRole(role)"
                  >
                    {{ t('roles.actions.delete') }}
                  </button>
                </PermissionGuard>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="drawerOpen"
      class="fixed inset-0 z-40 flex justify-start bg-black/30"
      @click.self="drawerOpen = false"
    >
      <aside class="flex h-full w-full max-w-md flex-col bg-white shadow-xl">
        <header class="border-b border-neutral-200 px-5 py-4">
          <h3 class="text-lg font-semibold">
            {{ editing ? t('roles.editTitle') : t('roles.createTitle') }}
          </h3>
        </header>
        <form class="flex flex-1 flex-col gap-4 p-5" @submit.prevent="submitForm">
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('roles.fields.name') }}</span>
            <input v-model="form.name" required class="w-full rounded-md border border-neutral-300 px-3 py-2" />
          </label>
          <label v-if="!editing" class="block text-sm">
            <span class="mb-1 block">{{ t('roles.fields.code') }}</span>
            <input v-model="form.code" class="w-full rounded-md border border-neutral-300 px-3 py-2 font-mono text-xs" :placeholder="t('roles.fields.codeHint')" />
          </label>
          <label v-else class="block text-sm text-neutral-500">
            {{ t('roles.fields.code') }}: <span class="font-mono">{{ form.code }}</span>
          </label>
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('roles.fields.description') }}</span>
            <textarea v-model="form.description" rows="3" class="w-full rounded-md border border-neutral-300 px-3 py-2" />
          </label>
          <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
          <div class="mt-auto flex gap-2">
            <button type="submit" class="rounded-md bg-emerald-800 px-4 py-2 text-sm text-white">{{ t('roles.save') }}</button>
            <button type="button" class="rounded-md border border-neutral-300 px-4 py-2 text-sm" @click="drawerOpen = false">{{ t('roles.cancel') }}</button>
          </div>
        </form>
      </aside>
    </div>
  </div>
</template>
