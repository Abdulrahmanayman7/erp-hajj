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
  return new Date(value).toLocaleDateString('ar-SA')
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
            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-primary-dark"
          >
            <Plus class="h-4 w-4" />
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
        <div
          class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
        >
          <div class="relative min-w-48 flex-1">
            <Search
              class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
              aria-hidden="true"
            />
            <input
              v-model="filters.search"
              type="search"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
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

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface px-4 py-10 text-center text-sm text-brand-text-secondary"
    >
      {{ t('platform.tenants.loading') }}
    </div>

    <div
      v-else-if="isError"
      class="rounded-2xl border border-red-200 bg-red-50 px-4 py-8 text-center"
    >
      <p class="text-sm text-red-800">{{ t('platform.tenants.loadError') }}</p>
      <button
        type="button"
        class="mt-3 inline-flex h-10 items-center rounded-xl bg-red-700 px-4 text-sm font-semibold text-white"
        @click="() => refetch()"
      >
        {{ t('platform.retry') }}
      </button>
    </div>

    <div
      v-else-if="tenants.length === 0"
      class="rounded-2xl border border-brand-border bg-brand-surface px-4 py-10 text-center text-sm text-brand-text-secondary"
    >
      {{ t('platform.tenants.empty') }}
    </div>

    <template v-else>
      <div class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface md:block">
        <table class="w-full min-w-[880px] border-collapse text-sm">
          <thead class="bg-brand-bg/80 text-start text-xs font-semibold uppercase tracking-wide text-brand-text-muted">
            <tr>
              <th class="px-4 py-3">{{ t('platform.columns.name') }}</th>
              <th class="px-4 py-3">{{ t('platform.columns.code') }}</th>
              <th class="px-4 py-3">{{ t('platform.columns.status') }}</th>
              <th class="px-4 py-3">{{ t('platform.columns.owner') }}</th>
              <th class="px-4 py-3">{{ t('platform.columns.contact') }}</th>
              <th class="px-4 py-3">{{ t('platform.columns.created') }}</th>
              <th class="px-4 py-3">{{ t('platform.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="tenant in tenants"
              :key="tenant.id"
              class="border-t border-brand-border/80 hover:bg-brand-bg/40"
            >
              <td class="px-4 py-3 font-semibold text-brand-text">
                <RouterLink
                  :to="{ name: 'platform-tenant-details', params: { id: tenant.id } }"
                  class="hover:text-brand-primary hover:underline"
                >
                  {{ tenant.name }}
                </RouterLink>
              </td>
              <td class="px-4 py-3 font-mono text-xs" dir="ltr">{{ tenant.code }}</td>
              <td class="px-4 py-3">
                <TenantStatusBadge :status="tenant.status" />
              </td>
              <td class="px-4 py-3">
                <div v-if="tenant.owner" class="min-w-0">
                  <div class="truncate font-medium">{{ tenant.owner.name }}</div>
                  <div class="truncate text-xs text-brand-text-muted" dir="ltr">{{ tenant.owner.email }}</div>
                </div>
                <span v-else class="text-brand-text-muted">—</span>
              </td>
              <td class="px-4 py-3">
                <div v-if="tenant.contact.name || tenant.contact.email" class="min-w-0">
                  <div v-if="tenant.contact.name" class="truncate">{{ tenant.contact.name }}</div>
                  <div
                    v-if="tenant.contact.email"
                    class="truncate text-xs text-brand-text-muted"
                    dir="ltr"
                  >
                    {{ tenant.contact.email }}
                  </div>
                </div>
                <span v-else class="text-brand-text-muted">—</span>
              </td>
              <td class="px-4 py-3 text-brand-text-secondary">{{ formatDate(tenant.created_at) }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-1">
                  <AppTooltip :text="t('platform.actions.view')">
                    <RouterLink
                      :to="{ name: 'platform-tenant-details', params: { id: tenant.id } }"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text-secondary hover:bg-brand-bg"
                    >
                      <Eye class="h-4 w-4" />
                    </RouterLink>
                  </AppTooltip>

                  <AppTooltip v-if="canActivate(tenant)" :text="t('platform.actions.activate')">
                    <button
                      type="button"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-emerald-700 hover:bg-emerald-50"
                      :disabled="lifecyclePending"
                      @click="onActivate(tenant)"
                    >
                      <PlayCircle class="h-4 w-4" />
                    </button>
                  </AppTooltip>

                  <AppTooltip v-if="canSuspend(tenant)" :text="t('platform.actions.suspend')">
                    <button
                      type="button"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-orange-700 hover:bg-orange-50"
                      :disabled="lifecyclePending"
                      @click="openReason(tenant, 'suspend')"
                    >
                      <PauseCircle class="h-4 w-4" />
                    </button>
                  </AppTooltip>

                  <AppTooltip v-if="canArchive(tenant)" :text="t('platform.actions.archive')">
                    <button
                      type="button"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-neutral-500 hover:bg-neutral-100"
                      :disabled="lifecyclePending"
                      @click="openReason(tenant, 'archive')"
                    >
                      <Archive class="h-4 w-4" />
                    </button>
                  </AppTooltip>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="space-y-3 md:hidden">
        <article
          v-for="tenant in tenants"
          :key="tenant.id"
          class="rounded-2xl border border-brand-border bg-brand-surface p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <RouterLink
                :to="{ name: 'platform-tenant-details', params: { id: tenant.id } }"
                class="font-semibold text-brand-text hover:underline"
              >
                {{ tenant.name }}
              </RouterLink>
              <p class="mt-0.5 font-mono text-xs text-brand-text-muted" dir="ltr">{{ tenant.code }}</p>
            </div>
            <TenantStatusBadge :status="tenant.status" />
          </div>

          <dl class="mt-3 space-y-1.5 text-sm">
            <div class="flex justify-between gap-3">
              <dt class="text-brand-text-muted">{{ t('platform.columns.owner') }}</dt>
              <dd class="truncate text-end">{{ tenant.owner?.name ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-brand-text-muted">{{ t('platform.columns.created') }}</dt>
              <dd>{{ formatDate(tenant.created_at) }}</dd>
            </div>
          </dl>

          <div class="mt-3 flex flex-wrap gap-2">
            <button
              v-if="canActivate(tenant)"
              type="button"
              class="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl bg-emerald-700 px-3 text-sm font-semibold text-white"
              :disabled="lifecyclePending"
              @click="onActivate(tenant)"
            >
              <PlayCircle class="h-4 w-4" />
              {{ t('platform.actions.activate') }}
            </button>
            <button
              v-if="canSuspend(tenant)"
              type="button"
              class="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl bg-orange-700 px-3 text-sm font-semibold text-white"
              :disabled="lifecyclePending"
              @click="openReason(tenant, 'suspend')"
            >
              <PauseCircle class="h-4 w-4" />
              {{ t('platform.actions.suspend') }}
            </button>
            <button
              v-if="canArchive(tenant)"
              type="button"
              class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl border border-brand-border px-3 text-sm font-medium text-brand-text-secondary"
              :disabled="lifecyclePending"
              @click="openReason(tenant, 'archive')"
            >
              <Archive class="h-4 w-4" />
              {{ t('platform.actions.archive') }}
            </button>
          </div>
        </article>
      </div>

      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3 rounded-2xl border border-brand-border bg-brand-surface px-4 py-3"
      >
        <button
          type="button"
          class="inline-flex h-10 items-center gap-1 rounded-xl border px-3 text-sm disabled:opacity-40"
          :disabled="filters.page <= 1 || isFetching"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" />
          {{ t('platform.prev') }}
        </button>
        <span class="text-sm text-brand-text-secondary">
          {{ t('platform.pageOf', { page: meta.current_page, total: meta.last_page }) }}
        </span>
        <button
          type="button"
          class="inline-flex h-10 items-center gap-1 rounded-xl border px-3 text-sm disabled:opacity-40"
          :disabled="filters.page >= meta.last_page || isFetching"
          @click="filters.page += 1"
        >
          {{ t('platform.next') }}
          <ChevronLeft class="h-4 w-4" />
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
