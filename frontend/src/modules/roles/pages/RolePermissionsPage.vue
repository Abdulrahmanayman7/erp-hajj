<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

import { ApiError } from '@/shared/api/http'
import { usePermissions } from '@/shared/composables/usePermissions'

import { useSyncRolePermissionsMutation } from '../mutations/useRoleMutations'
import { usePermissionsCatalogQuery, useRoleQuery } from '../queries/useRolesQuery'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()

const roleId = computed(() => Number(route.params.id))
const search = ref('')

const { data: role, isLoading: roleLoading, isError: roleError } = useRoleQuery(roleId)
const { data: modules, isLoading: catalogLoading } = usePermissionsCatalogQuery(search)
const syncMutation = useSyncRolePermissionsMutation()

const selected = ref<number[]>([])
const errorMessage = ref('')
const successMessage = ref('')

watch(
  role,
  (value) => {
    if (!value) {
      return
    }
    const names = new Set(value.permissions ?? [])
    const ids: number[] = []
    for (const group of modules.value ?? []) {
      for (const permission of group.permissions) {
        if (names.has(permission.name)) {
          ids.push(permission.id)
        }
      }
    }
    if (ids.length > 0 || (value.permissions?.length ?? 0) === 0) {
      selected.value = ids
    }
  },
  { immediate: true },
)

watch(
  modules,
  (groups) => {
    if (!role.value?.permissions || !groups) {
      return
    }
    const names = new Set(role.value.permissions)
    selected.value = groups
      .flatMap((group) => group.permissions)
      .filter((permission) => names.has(permission.name))
      .map((permission) => permission.id)
  },
)

const selectedCount = computed(() => selected.value.length)
const canSave = computed(() => can('roles.assign_permissions'))

function togglePermission(id: number): void {
  if (selected.value.includes(id)) {
    selected.value = selected.value.filter((item) => item !== id)
  } else {
    selected.value = [...selected.value, id]
  }
}

function toggleModule(moduleName: string): void {
  const group = (modules.value ?? []).find((item) => item.module === moduleName)
  if (!group) {
    return
  }
  const ids = group.permissions.map((permission) => permission.id)
  const allSelected = ids.every((id) => selected.value.includes(id))
  if (allSelected) {
    selected.value = selected.value.filter((id) => !ids.includes(id))
  } else {
    selected.value = Array.from(new Set([...selected.value, ...ids]))
  }
}

async function save(): Promise<void> {
  errorMessage.value = ''
  successMessage.value = ''
  try {
    await syncMutation.mutateAsync({
      id: roleId.value,
      permissionIds: selected.value,
    })
    successMessage.value = t('roles.matrix.saved')
  } catch (error) {
    errorMessage.value = error instanceof ApiError ? error.message : t('roles.errors.generic')
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <button type="button" class="text-sm text-emerald-800 hover:underline" @click="router.push('/app/roles')">
          ← {{ t('roles.backToList') }}
        </button>
        <h2 class="mt-2 text-2xl font-semibold text-neutral-900">
          {{ t('roles.matrix.title') }}
          <span v-if="role" class="text-neutral-500">— {{ role.name }}</span>
        </h2>
        <p class="mt-1 text-sm text-neutral-500">
          {{ t('roles.matrix.selected', { count: selectedCount }) }}
        </p>
      </div>
      <button
        v-if="canSave"
        type="button"
        class="rounded-md bg-emerald-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
        :disabled="syncMutation.isPending.value"
        @click="save"
      >
        {{ t('roles.matrix.save') }}
      </button>
    </div>

    <input
      v-model="search"
      type="search"
      class="w-full max-w-md rounded-md border border-neutral-300 px-3 py-2 text-sm"
      :placeholder="t('roles.matrix.search')"
    />

    <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>
    <p v-if="successMessage" class="text-sm text-emerald-700">{{ successMessage }}</p>

    <div v-if="roleLoading || catalogLoading" class="rounded-xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500">
      {{ t('roles.loading') }}
    </div>
    <div v-else-if="roleError" class="rounded-xl border border-red-200 bg-red-50 p-8 text-center text-sm text-red-700">
      {{ t('roles.errors.load') }}
    </div>
    <div v-else class="space-y-4">
      <section
        v-for="group in modules ?? []"
        :key="group.module"
        class="rounded-xl border border-neutral-200 bg-white p-4"
      >
        <div class="mb-3 flex items-center justify-between gap-3">
          <h3 class="font-semibold text-neutral-900">{{ group.display_name }}</h3>
          <button
            v-if="canSave"
            type="button"
            class="text-xs text-emerald-800 hover:underline"
            @click="toggleModule(group.module)"
          >
            {{ t('roles.matrix.toggleModule') }}
          </button>
        </div>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <label
            v-for="permission in group.permissions"
            :key="permission.id"
            class="flex items-start gap-2 rounded-md border border-neutral-100 px-3 py-2 text-sm"
            :title="permission.name"
          >
            <input
              type="checkbox"
              class="mt-1"
              :checked="selected.includes(permission.id)"
              :disabled="!canSave"
              @change="togglePermission(permission.id)"
            />
            <span>
              <span class="block font-medium">{{ permission.display_name }}</span>
              <span v-if="permission.high_risk" class="text-xs text-amber-700">{{ t('roles.matrix.highRisk') }}</span>
            </span>
          </label>
        </div>
      </section>
    </div>
  </div>
</template>
