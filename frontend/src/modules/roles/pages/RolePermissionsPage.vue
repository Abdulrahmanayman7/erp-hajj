<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, Check, Loader2, Search, ShieldAlert } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import { useSyncRolePermissionsMutation } from '../mutations/useRoleMutations'
import { usePermissionsCatalogQuery, useRoleQuery } from '../queries/useRolesQuery'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()

const roleId = computed(() => Number(route.params.id))
const search = ref('')

const { data: role, isLoading: roleLoading, isError: roleError } = useRoleQuery(roleId)
const { data: modules, isLoading: catalogLoading } = usePermissionsCatalogQuery(search)
const syncMutation = useSyncRolePermissionsMutation()

const selected = ref<number[]>([])
const errorMessage = ref('')

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

watch(modules, (groups) => {
  if (!role.value?.permissions || !groups) {
    return
  }
  const names = new Set(role.value.permissions)
  selected.value = groups
    .flatMap((group) => group.permissions)
    .filter((permission) => names.has(permission.name))
    .map((permission) => permission.id)
})

const selectedCount = computed(() => selected.value.length)
const canSave = computed(() => can('roles.assign_permissions'))
const isSaving = computed(() => syncMutation.isPending.value)
const hasModules = computed(() => (modules.value?.length ?? 0) > 0)
const headerMeta = computed(() => {
  if (!role.value) return undefined
  return t('roles.matrix.selected', { count: selectedCount.value })
})

function isSelected(id: number): boolean {
  return selected.value.includes(id)
}

function moduleStats(moduleName: string): { selected: number; total: number; allSelected: boolean } {
  const group = (modules.value ?? []).find((item) => item.module === moduleName)
  if (!group) {
    return { selected: 0, total: 0, allSelected: false }
  }
  const ids = group.permissions.map((permission) => permission.id)
  const count = ids.filter((id) => selected.value.includes(id)).length
  return {
    selected: count,
    total: ids.length,
    allSelected: ids.length > 0 && count === ids.length,
  }
}

function togglePermission(id: number): void {
  if (!canSave.value) {
    return
  }
  if (selected.value.includes(id)) {
    selected.value = selected.value.filter((item) => item !== id)
  } else {
    selected.value = [...selected.value, id]
  }
}

function toggleModule(moduleName: string): void {
  if (!canSave.value) {
    return
  }
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
  try {
    await syncMutation.mutateAsync({
      id: roleId.value,
      permissionIds: selected.value,
    })
    toast.success(t('roles.matrix.saved'))
  } catch (error) {
    errorMessage.value = error instanceof ApiError ? error.message : t('roles.errors.generic')
    toast.error(errorMessage.value)
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="role ? `${t('roles.matrix.title')} — ${role.name}` : t('roles.matrix.title')"
      :subtitle="t('roles.matrix.subtitle')"
      :meta="headerMeta"
    >
      <template #actions>
        <button
          type="button"
          class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-text transition hover:bg-brand-bg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/20 sm:w-auto"
          @click="router.push('/app/roles')"
        >
          <ArrowRight class="h-4 w-4" :stroke-width="2" />
          <span>{{ t('roles.backToList') }}</span>
        </button>

        <button
          v-if="canSave"
          type="button"
          class="hidden h-11 min-w-[9rem] items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary disabled:cursor-not-allowed disabled:opacity-60 sm:inline-flex"
          :disabled="isSaving"
          @click="save"
        >
          <Loader2
            v-if="isSaving"
            class="h-4 w-4 animate-spin"
            :stroke-width="2.25"
            aria-hidden="true"
          />
          <span>{{ isSaving ? t('roles.matrix.saving') : t('roles.matrix.save') }}</span>
        </button>
      </template>
    </AppPageHeader>

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
          v-model="search"
          type="search"
          class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
          :placeholder="t('roles.matrix.search')"
        />
      </div>
      <p
        v-if="!canSave"
        class="w-full text-xs font-semibold text-brand-text-muted sm:w-auto"
      >
        {{ t('roles.matrix.readOnlyHint') }}
      </p>
    </div>

    <p
      v-if="errorMessage"
      class="rounded-[11px] border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-700"
      role="alert"
    >
      {{ errorMessage }}
    </p>

    <div
      v-if="roleLoading || catalogLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('roles.loading') }}
    </div>
    <div
      v-else-if="roleError"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center text-sm text-red-700"
    >
      {{ t('roles.errors.load') }}
    </div>
    <div
      v-else-if="!hasModules"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('roles.matrix.empty') }}
    </div>
    <div v-else class="space-y-4">
      <section
        v-for="group in modules ?? []"
        :key="group.module"
        class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
      >
        <div
          class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border bg-[#F7F8F6] px-5 py-4"
        >
          <div class="min-w-0">
            <h3 class="text-[15px] font-bold text-brand-text">
              {{ group.display_name }}
            </h3>
            <p class="mt-1 text-xs font-semibold text-brand-text-muted">
              {{
                t('roles.matrix.moduleSelected', {
                  count: moduleStats(group.module).selected,
                  total: moduleStats(group.module).total,
                })
              }}
            </p>
          </div>
          <button
            v-if="canSave"
            type="button"
            class="inline-flex h-11 min-h-[44px] items-center rounded-lg border border-brand-border bg-brand-surface px-3 text-xs font-semibold text-brand-primary-dark transition hover:bg-brand-primary-soft"
            @click="toggleModule(group.module)"
          >
            {{
              moduleStats(group.module).allSelected
                ? t('roles.matrix.clearAll')
                : t('roles.matrix.selectAll')
            }}
          </button>
        </div>

        <div class="grid gap-2.5 p-4 sm:grid-cols-2 xl:grid-cols-3">
          <button
            v-for="permission in group.permissions"
            :key="permission.id"
            type="button"
            class="flex min-h-[64px] items-start gap-3 rounded-[11px] border px-3.5 py-3 text-start transition duration-[160ms] ease-out"
            :class="[
              !canSave ? 'cursor-default' : 'cursor-pointer',
              isSelected(permission.id)
                ? permission.high_risk
                  ? 'border-brand-gold/55 bg-brand-gold-soft'
                  : 'border-brand-primary bg-brand-primary-soft'
                : permission.high_risk
                  ? 'border-brand-gold/30 bg-brand-surface hover:bg-brand-gold-soft/45'
                  : 'border-brand-border bg-brand-surface hover:border-brand-primary/25 hover:bg-brand-primary-soft/50',
              !canSave && !isSelected(permission.id) ? 'opacity-80' : '',
            ]"
            :disabled="!canSave"
            :title="permission.name"
            @click="togglePermission(permission.id)"
          >
            <span
              class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded border transition"
              :class="
                isSelected(permission.id)
                  ? permission.high_risk
                    ? 'border-brand-gold bg-brand-gold text-white'
                    : 'border-brand-primary bg-brand-primary text-white'
                  : 'border-brand-border bg-brand-surface'
              "
              aria-hidden="true"
            >
              <Check
                v-if="isSelected(permission.id)"
                class="h-3 w-3"
                :stroke-width="3"
              />
            </span>

            <span class="min-w-0 flex-1">
              <span class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-brand-text">
                  {{ permission.display_name }}
                </span>
                <span
                  v-if="permission.high_risk"
                  class="inline-flex items-center gap-1 rounded-md bg-brand-gold-soft px-1.5 py-0.5 text-[11px] font-semibold text-[#8A6A2E] ring-1 ring-brand-gold/35"
                >
                  <ShieldAlert class="h-3 w-3" :stroke-width="2" />
                  {{ t('roles.matrix.highRisk') }}
                </span>
              </span>
              <span
                v-if="permission.description"
                class="mt-1 block text-[12px] leading-relaxed text-brand-text-secondary"
              >
                {{ permission.description }}
              </span>
            </span>
          </button>
        </div>
      </section>
    </div>

    <div
      v-if="canSave && hasModules && !roleLoading && !catalogLoading"
      class="app-sticky-form-actions sm:hidden"
    >
      <div class="app-sticky-form-actions__inner">
        <button
          type="button"
          class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="isSaving"
          @click="save"
        >
          <Loader2
            v-if="isSaving"
            class="h-4 w-4 animate-spin"
            :stroke-width="2.25"
            aria-hidden="true"
          />
          <span>{{ isSaving ? t('roles.matrix.saving') : t('roles.matrix.save') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
