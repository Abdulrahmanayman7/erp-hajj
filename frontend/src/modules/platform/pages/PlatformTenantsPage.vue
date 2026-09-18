<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import {
  Archive,
  ChevronLeft,
  ChevronRight,
  Eye,
  PauseCircle,
  PlayCircle,
  Plus,
  Search,
} from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect from '@/shared/components/AppSelect.vue'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import TenantLifecycleReasonDialog from '../components/TenantLifecycleReasonDialog.vue'
import TenantStatusBadge from '../components/TenantStatusBadge.vue'
import {
  useActivatePlatformTenantMutation,
  useArchivePlatformTenantMutation,
  useSuspendPlatformTenantMutation,
} from '../mutations/usePlatformTenantMutations'
import { usePlatformTenantsQuery } from '../queries/usePlatformTenantsQuery'
import type { PlatformTenant, PlatformTenantStatus } from '../types/platform'
import { validateLifecycleReason } from '../validation/platformValidation'

const { t } = useI18n()
const { can } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()

const filters = reactive({
  search: '',
  status: '' as '' | PlatformTenantStatus,
  page: 1,
  per_page: 15,
})

const committedSearch = useDebouncedRef(() => filters.search)
const queryParams = computed(() => ({ ...filters, search: committedSearch.value }))
const { data, isLoading, isError, refetch, isFetching } = usePlatformTenantsQuery(queryParams)

const activateMutation = useActivatePlatformTenantMutation()
const suspendMutation = useSuspendPlatformTenantMutation()
const archiveMutation = useArchivePlatformTenantMutation()

const tenants = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)

const statusOptions = computed(() => [
  { value: '', label: t('platform.filters.allStatuses') },
  { value: 'active', label: t('platform.status.active') },
  { value: 'pending', label: t('platform.status.pending') },
  { value: 'suspended', label: t('platform.status.suspended') },
  { value: 'archived', label: t('platform.status.archived') },
])

const activeFilterCount = computed(() => (filters.status ? 1 : 0))

const reasonOpen = ref(false)
const reasonKind = ref<'suspend' | 'archive' | null>(null)
const reasonTenant = ref<PlatformTenant | null>(null)
const reason = ref('')
const reasonFieldError = ref('')
const reasonFormError = ref('')

const lifecyclePending = computed(
  () =>
    activateMutation.isPending.value ||
    suspendMutation.isPending.value ||
    archiveMutation.isPending.value,
)

watch(
  () => [committedSearch.value, filters.status],
  () => {
    filters.page = 1
  },
)

function resetFilters(): void {
  filters.status = ''
}

function formatDate(value: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

function apiMessage(error: unknown): string {
  if (error instanceof ApiError) {
    return error.message || t('platform.errors.generic')
  }
  return t('platform.errors.generic')
}

async function onActivate(tenant: PlatformTenant): Promise<void> {
  const ok = await confirm({
    title: t('platform.lifecycle.activateTitle'),
    message: t('platform.lifecycle.activateBody', { name: tenant.name }),
    confirmLabel: t('platform.actions.activate'),
    variant: 'primary',
  })
  if (!ok) return

  try {
    await activateMutation.mutateAsync(tenant.id)
    toast.success(t('platform.success.activate'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function openReason(tenant: PlatformTenant, kind: 'suspend' | 'archive'): void {
  reasonTenant.value = tenant
  reasonKind.value = kind
  reason.value = ''
  reasonFieldError.value = ''
  reasonFormError.value = ''
  reasonOpen.value = true
}

function closeReason(): void {
  reasonOpen.value = false
  reasonKind.value = null
  reasonTenant.value = null
}

async function submitReason(): Promise<void> {
  if (!reasonTenant.value || !reasonKind.value) return

  reasonFieldError.value = ''
  reasonFormError.value = ''
  const validation = validateLifecycleReason(reason.value)
  if (validation.reason) {
    reasonFieldError.value = t(`platform.validation.${validation.reason}`)
    return
  }

  try {
    if (reasonKind.value === 'suspend') {
      await suspendMutation.mutateAsync({
        id: reasonTenant.value.id,
        reason: reason.value.trim(),
      })
      toast.success(t('platform.success.suspend'))
    } else {
      await archiveMutation.mutateAsync({
        id: reasonTenant.value.id,
        reason: reason.value.trim(),
      })
      toast.success(t('platform.success.archive'))
    }
    closeReason()
  } catch (error) {
    reasonFormError.value = apiMessage(error)
  }
}

function canActivate(tenant: PlatformTenant): boolean {
  return can('platform_tenants.activate') && (tenant.status === 'pending' || tenant.status === 'suspended')
}

function canSuspend(tenant: PlatformTenant): boolean {
  return can('platform_tenants.suspend') && tenant.status === 'active'
}

function canArchive(tenant: PlatformTenant): boolean {
  return can('platform_tenants.archive') && tenant.status !== 'archived'
}
</script>

<template>
  <div class="space-y-5">
    <AppPageHeader
      :title="t('platform.tenants.title')"
      :subtitle="t('platform.tenants.subtitle')"
      :meta="meta ? t('platform.tenants.total', { count: meta.total }) : undefined"
    >
      <template #actions>
        <PermissionGuard permission="platform_tenants.create">
          <RouterLink
            :to="{ name: 'platform-tenants-create' }"
            class="app-btn-primary"
          >
            <Plus class="h-4 w-4" :stroke-width="2.25" />
            {{ t('platform.tenants.add') }}
          </RouterLink>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <AppMobileFilters
      v-model:search="filters.search"
      :search-placeholder="t('platform.tenants.searchPlaceholder')"
      :active-count="activeFilterCount"
      @reset="resetFilters"
    >
      <template #desktop>
        <div class="app-surface-flat flex flex-wrap items-center gap-3 p-3.5">
          <div class="relative min-w-48 flex-1">
            <Search
              class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
              :stroke-width="1.75"
              aria-hidden="true"
            />
            <input
              v-model="filters.search"
              type="search"
              class="app-input app-input--search"
              :placeholder="t('platform.tenants.searchPlaceholder')"
            />
          </div>
          <div class="w-full sm:w-52">
            <AppSelect
              v-model="filters.status"
              :options="statusOptions"
              :aria-label="t('platform.filters.status')"
            />
          </div>
        </div>
      </template>

      <template #filters>
        <AppSelect
          v-model="filters.status"
          :options="statusOptions"
          :aria-label="t('platform.filters.status')"
        />
      </template>
    </AppMobileFilters>

    <div v-if="isLoading" class="space-y-3" aria-busy="true">
      <div
        v-for="n in 4"
        :key="n"
        class="h-[7.5rem] animate-pulse rounded-[14px] border border-brand-border bg-brand-surface lg:h-14"
      />
    </div>

    <div
      v-else-if="isError"
      class="rounded-[14px] border border-red-200/80 bg-[var(--danger-soft)] p-8 text-center"
      role="alert"
    >
      <p class="text-sm text-red-800">{{ t('platform.tenants.loadError') }}</p>
      <button
        type="button"
        class="mt-3 inline-flex min-h-11 items-center justify-center px-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('platform.retry') }}
      </button>
    </div>

    <div
      v-else-if="tenants.length === 0"
      class="app-surface-flat px-6 py-10 text-center sm:py-12"
    >
      <p class="text-base font-semibold text-brand-text">{{ t('platform.tenants.empty') }}</p>
      <p class="mt-1 text-sm text-brand-text-muted">{{ t('platform.tenants.emptyHint') }}</p>
      <PermissionGuard permission="platform_tenants.create">
        <RouterLink
          :to="{ name: 'platform-tenants-create' }"
          class="app-btn-primary mt-5"
        >
          <Plus class="h-4 w-4" :stroke-width="2.25" />
          {{ t('platform.tenants.add') }}
        </RouterLink>
      </PermissionGuard>
    </div>

    <template v-else>
      <div class="hidden overflow-hidden rounded-[14px] border border-brand-border bg-brand-surface lg:block">
        <div class="overflow-x-auto">
          <table class="min-w-full border-separate border-spacing-0 text-sm">
            <thead>
              <tr class="bg-[var(--surface-subtle)]">
                <th class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-semibold text-brand-text">
                  {{ t('platform.columns.name') }}
                </th>
                <th class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-semibold text-brand-text">
                  {{ t('platform.columns.status') }}
                </th>
                <th class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-semibold text-brand-text">
                  {{ t('platform.columns.owner') }}
                </th>
                <th class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-semibold text-brand-text">
                  {{ t('platform.columns.contact') }}
                </th>
                <th class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-semibold text-brand-text">
                  {{ t('platform.columns.created') }}
                </th>
                <th class="w-36 whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-semibold text-brand-text">
                  {{ t('platform.columns.actions') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(tenant, index) in tenants"
                :key="tenant.id"
                class="group"
                :class="index % 2 === 1 ? 'bg-[var(--surface-subtle)]' : 'bg-brand-surface'"
              >
                <td class="max-w-[16rem] border-b border-brand-border/80 px-5 py-3.5 group-hover:bg-[#EDF6F1]">
                  <RouterLink
                    :to="{ name: 'platform-tenant-details', params: { id: tenant.id } }"
                    class="block min-w-0"
                  >
                    <span class="block truncate font-semibold text-brand-text hover:text-brand-primary-dark">
                      {{ tenant.name }}
                    </span>
                    <span class="mt-0.5 block truncate font-mono text-[11px] text-brand-text-muted" dir="ltr">
                      {{ tenant.code }}
                    </span>
                  </RouterLink>
                </td>
                <td class="border-b border-brand-border/80 px-5 py-3.5 group-hover:bg-[#EDF6F1]">
                  <TenantStatusBadge :status="tenant.status" />
                </td>
                <td class="max-w-[14rem] border-b border-brand-border/80 px-5 py-3.5 group-hover:bg-[#EDF6F1]">
                  <div v-if="tenant.owner" class="min-w-0">
                    <p class="truncate font-medium text-brand-text">{{ tenant.owner.name }}</p>
                    <p class="truncate text-xs text-brand-text-muted" dir="ltr">{{ tenant.owner.email }}</p>
                  </div>
                  <span v-else class="text-brand-text-muted">—</span>
                </td>
                <td class="max-w-[14rem] border-b border-brand-border/80 px-5 py-3.5 group-hover:bg-[#EDF6F1]">
                  <div v-if="tenant.contact.name || tenant.contact.email" class="min-w-0">
                    <p v-if="tenant.contact.name" class="truncate text-brand-text">{{ tenant.contact.name }}</p>
                    <p
                      v-if="tenant.contact.email"
                      class="truncate text-xs text-brand-text-muted"
                      dir="ltr"
                    >
                      {{ tenant.contact.email }}
                    </p>
                  </div>
                  <span v-else class="text-brand-text-muted">—</span>
                </td>
                <td class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-brand-text-secondary group-hover:bg-[#EDF6F1]">
                  {{ formatDate(tenant.created_at) }}
                </td>
                <td class="border-b border-brand-border/80 px-5 py-3.5 group-hover:bg-[#EDF6F1]">
                  <div class="flex items-center justify-center gap-0.5">
                    <AppTooltip :text="t('platform.actions.view')">
                      <RouterLink
                        :to="{ name: 'platform-tenant-details', params: { id: tenant.id } }"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-[8px] text-brand-text-secondary transition hover:bg-brand-surface hover:text-brand-primary-dark"
                        :aria-label="t('platform.actions.view')"
                      >
                        <Eye class="h-4 w-4" :stroke-width="2" />
                      </RouterLink>
                    </AppTooltip>

                    <AppTooltip v-if="canActivate(tenant)" :text="t('platform.actions.activate')">
                      <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-[8px] text-emerald-700 transition hover:bg-emerald-50"
                        :aria-label="t('platform.actions.activate')"
                        :disabled="lifecyclePending"
                        @click="onActivate(tenant)"
                      >
                        <PlayCircle class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>

                    <AppTooltip v-if="canSuspend(tenant)" :text="t('platform.actions.suspend')">
                      <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-[8px] text-orange-700 transition hover:bg-orange-50"
                        :aria-label="t('platform.actions.suspend')"
                        :disabled="lifecyclePending"
                        @click="openReason(tenant, 'suspend')"
                      >
                        <PauseCircle class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>

                    <AppTooltip v-if="canArchive(tenant)" :text="t('platform.actions.archive')">
                      <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-[8px] text-brand-text-muted transition hover:bg-[var(--surface-muted)]"
                        :aria-label="t('platform.actions.archive')"
                        :disabled="lifecyclePending"
                        @click="openReason(tenant, 'archive')"
                      >
                        <Archive class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="space-y-2.5 lg:hidden">
        <article
          v-for="tenant in tenants"
          :key="tenant.id"
          class="app-surface-flat p-3.5"
        >
          <div class="flex items-start justify-between gap-3">
            <RouterLink
              :to="{ name: 'platform-tenant-details', params: { id: tenant.id } }"
              class="min-w-0 flex-1"
            >
              <p class="app-type-card truncate">{{ tenant.name }}</p>
              <p class="mt-0.5 font-mono text-[11px] text-brand-text-muted" dir="ltr">{{ tenant.code }}</p>
            </RouterLink>
            <TenantStatusBadge :status="tenant.status" />
          </div>

          <dl class="mt-3 grid grid-cols-1 gap-2 text-[13px] sm:grid-cols-2">
            <div class="min-w-0">
              <dt class="text-xs text-brand-text-muted">{{ t('platform.columns.owner') }}</dt>
              <dd class="mt-0.5 truncate font-medium text-brand-text">{{ tenant.owner?.name ?? '—' }}</dd>
            </div>
            <div class="min-w-0">
              <dt class="text-xs text-brand-text-muted">{{ t('platform.columns.created') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text">{{ formatDate(tenant.created_at) }}</dd>
            </div>
            <div v-if="tenant.contact.name || tenant.contact.email" class="min-w-0 sm:col-span-2">
              <dt class="text-xs text-brand-text-muted">{{ t('platform.columns.contact') }}</dt>
              <dd class="mt-0.5 truncate font-medium text-brand-text">
                {{ tenant.contact.name || tenant.contact.email }}
              </dd>
            </div>
          </dl>

          <div class="mt-3 flex flex-wrap gap-2 border-t border-[var(--border-soft)] pt-3">
            <RouterLink
              :to="{ name: 'platform-tenant-details', params: { id: tenant.id } }"
              class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-[10px] border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text"
            >
              <Eye class="h-4 w-4" :stroke-width="2" />
              {{ t('platform.actions.view') }}
            </RouterLink>
            <button
              v-if="canActivate(tenant)"
              type="button"
              class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-[10px] bg-emerald-700 px-3 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="lifecyclePending"
              @click="onActivate(tenant)"
            >
              <PlayCircle class="h-4 w-4" :stroke-width="2" />
              {{ t('platform.actions.activate') }}
            </button>
            <button
              v-if="canSuspend(tenant)"
              type="button"
              class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-[10px] bg-orange-700 px-3 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="lifecyclePending"
              @click="openReason(tenant, 'suspend')"
            >
              <PauseCircle class="h-4 w-4" :stroke-width="2" />
              {{ t('platform.actions.suspend') }}
            </button>
            <button
              v-if="canArchive(tenant)"
              type="button"
              class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-[10px] border border-brand-border px-3 text-sm font-medium text-brand-text-secondary disabled:opacity-60"
              :disabled="lifecyclePending"
              @click="openReason(tenant, 'archive')"
            >
              <Archive class="h-4 w-4" :stroke-width="2" />
              {{ t('platform.actions.archive') }}
            </button>
          </div>
        </article>
      </div>

      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3"
      >
        <button
          type="button"
          class="inline-flex h-11 items-center gap-1 rounded-[10px] border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-[var(--surface-subtle)] disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page <= 1 || isFetching"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" :stroke-width="2" />
          {{ t('platform.prev') }}
        </button>
        <span class="text-xs font-semibold text-brand-text-muted">
          {{ t('platform.pageOf', { page: meta.current_page, total: meta.last_page }) }}
        </span>
        <button
          type="button"
          class="inline-flex h-11 items-center gap-1 rounded-[10px] border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-[var(--surface-subtle)] disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page >= meta.last_page || isFetching"
          @click="filters.page += 1"
        >
          {{ t('platform.next') }}
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
        </button>
      </div>
    </template>

    <TenantLifecycleReasonDialog
      :open="reasonOpen"
      :kind="reasonKind"
      :reason="reason"
      :form-error="reasonFormError"
      :field-error="reasonFieldError"
      :submitting="lifecyclePending"
      @close="closeReason"
      @submit="submitReason"
      @update:reason="reason = $event"
    />
  </div>
</template>
