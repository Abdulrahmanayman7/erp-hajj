<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { Plus, RefreshCw } from 'lucide-vue-next'

import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { ApiError } from '@/shared/api/http'
import GpsProviderFormDrawer, {
  type GpsProviderFormState,
} from '../components/GpsProviderFormDrawer.vue'
import {
  createGpsProvider,
  deleteGpsProvider,
  listGpsProviders,
  syncGpsProvider,
  testGpsProvider,
  updateGpsProvider,
} from '../api/gpsProvidersApi'
import type { GpsConnectionMethod, GpsProvider } from '../types/gpsProviders'
import {
  gpsToneClass,
  isProviderVerified,
  providerLifecycleLabel,
  providerMethodLabelKey,
  providerHealthLabelKey,
} from '../utils/gpsStatus'

const { t } = useI18n()
const { can } = usePermissions()
const { confirm } = useConfirm()
const queryClient = useQueryClient()

const canView = computed(() => can('gps.providers.view') || can('gps.providers.manage'))
const canManage = computed(() => can('gps.providers.manage'))

const selectedId = ref<number | null>(null)
const formOpen = ref(false)
const formError = ref<string | null>(null)
const editing = ref<GpsProvider | null>(null)
const flash = ref<string | null>(null)

const emptyForm = (): GpsProviderFormState => ({
  name: '',
  code: '',
  connection_method: 'webhook',
  is_enabled: false,
  poll_interval_seconds: '300',
  base_url: '',
  endpoint_url: '',
  webhook_secret: '',
  api_token: '',
  email: '',
  password: '',
})

const form = ref<GpsProviderFormState>(emptyForm())

const providersQuery = useQuery({
  queryKey: ['gps-providers'],
  queryFn: listGpsProviders,
  enabled: canView,
})

const selected = computed(
  () => providersQuery.data.value?.find((row) => row.id === selectedId.value) ?? null,
)

const summary = computed(() => {
  const rows = providersQuery.data.value ?? []
  return {
    total: rows.length,
    enabled: rows.filter((row) => row.is_enabled).length,
    verified: rows.filter((row) => isProviderVerified(row)).length,
  }
})

const simFailure = ref(false)
const simOfflineDevices = ref('')
const simRadius = ref('3')
const simPoll = ref('60')

watch(selected, (row) => {
  if (!row || row.connection_method !== 'simulator') return
  simFailure.value = Boolean(row.settings.simulate_failure)
  const offline = row.settings.offline_device_ids
  simOfflineDevices.value = Array.isArray(offline) ? offline.map(String).join(', ') : ''
  simRadius.value = row.settings.radius_km != null ? String(row.settings.radius_km) : '3'
  simPoll.value = row.poll_interval_seconds != null ? String(row.poll_interval_seconds) : '60'
})

watch(
  () => providersQuery.data.value,
  (rows) => {
    if (rows && rows.length > 0 && selectedId.value == null) {
      selectedId.value = rows[0]!.id
    }
  },
)

function openCreate() {
  editing.value = null
  form.value = emptyForm()
  formError.value = null
  formOpen.value = true
}

function openEdit(row: GpsProvider) {
  editing.value = row
  form.value = {
    name: row.name,
    code: row.code,
    connection_method: row.connection_method,
    is_enabled: row.is_enabled,
    poll_interval_seconds: row.poll_interval_seconds != null ? String(row.poll_interval_seconds) : '',
    base_url: String(row.settings.base_url ?? ''),
    endpoint_url: String(row.settings.endpoint_url ?? ''),
    webhook_secret: '',
    api_token: '',
    email: '',
    password: '',
  }
  formError.value = null
  formOpen.value = true
}

async function closeForm() {
  if (saveMutation.isPending.value) return
  const dirty =
    form.value.name.trim() !== (editing.value?.name ?? '') ||
    form.value.webhook_secret.trim() !== '' ||
    form.value.api_token.trim() !== '' ||
    form.value.password.trim() !== ''
  if (dirty) {
    const ok = await confirm({
      title: t('gpsProviders.unsavedTitle'),
      message: t('gpsProviders.unsavedMessage'),
      confirmLabel: t('gpsProviders.discard'),
      cancelLabel: t('gpsProviders.cancel'),
      variant: 'warning',
    })
    if (!ok) return
  }
  formOpen.value = false
}

const saveMutation = useMutation({
  mutationFn: async () => {
    const settings: Record<string, unknown> = {}
    if (form.value.base_url.trim()) settings.base_url = form.value.base_url.trim()
    if (form.value.endpoint_url.trim()) settings.endpoint_url = form.value.endpoint_url.trim()
    if (form.value.connection_method === 'mobile_driver') {
      settings.require_explicit_consent = true
      settings.tracking_enabled = false
    }
    if (form.value.connection_method === 'simulator') {
      settings.base_latitude = 21.4225
      settings.base_longitude = 39.8262
      settings.radius_km = 3
    }

    const credentials: Record<string, string> = {}
    if (form.value.webhook_secret.trim()) credentials.webhook_secret = form.value.webhook_secret.trim()
    if (form.value.api_token.trim()) credentials.api_token = form.value.api_token.trim()
    if (form.value.email.trim()) credentials.email = form.value.email.trim()
    if (form.value.password.trim()) credentials.password = form.value.password.trim()

    const poll =
      form.value.poll_interval_seconds.trim() === ''
        ? null
        : Number(form.value.poll_interval_seconds)

    if (editing.value != null) {
      return updateGpsProvider(editing.value.id, {
        name: form.value.name.trim(),
        connection_method: form.value.connection_method,
        is_enabled: form.value.is_enabled,
        poll_interval_seconds: poll,
        settings,
        credentials: Object.keys(credentials).length ? credentials : undefined,
      })
    }

    return createGpsProvider({
      name: form.value.name.trim(),
      code: form.value.code.trim(),
      connection_method: form.value.connection_method,
      is_enabled: form.value.is_enabled,
      poll_interval_seconds: poll,
      settings,
      credentials: Object.keys(credentials).length ? credentials : undefined,
    })
  },
  onSuccess: async (row) => {
    flash.value = t('gpsProviders.saved')
    formOpen.value = false
    formError.value = null
    await queryClient.invalidateQueries({ queryKey: ['gps-providers'] })
    selectedId.value = row.id
  },
  onError: (error) => {
    formError.value =
      error instanceof ApiError ? error.message : t('gpsProviders.saveError')
  },
})

const testMutation = useMutation({
  mutationFn: (id: number) => {
    flash.value = null
    return testGpsProvider(id)
  },
  onSuccess: async (result) => {
    flash.value = result.message
    await queryClient.invalidateQueries({ queryKey: ['gps-providers'] })
  },
})

const syncMutation = useMutation({
  mutationFn: (id: number) => {
    flash.value = null
    return syncGpsProvider(id)
  },
  onSuccess: async (result) => {
    flash.value = result.message
    await queryClient.invalidateQueries({ queryKey: ['gps-providers'] })
  },
})

const deleteMutation = useMutation({
  mutationFn: (id: number) => deleteGpsProvider(id),
  onSuccess: async () => {
    flash.value = t('gpsProviders.deleted')
    selectedId.value = null
    await queryClient.invalidateQueries({ queryKey: ['gps-providers'] })
  },
})

async function onDelete(row: GpsProvider) {
  const ok = await confirm({
    title: t('gpsProviders.deleteTitle'),
    message: t('gpsProviders.deleteMessage', { name: row.name }),
    confirmLabel: t('gpsProviders.delete'),
    cancelLabel: t('gpsProviders.cancel'),
    variant: 'danger',
  })
  if (ok) deleteMutation.mutate(row.id)
}

const simulatorMutation = useMutation({
  mutationFn: async () => {
    if (selectedId.value == null) throw new Error('no provider')
    const offline = simOfflineDevices.value
      .split(',')
      .map((part) => part.trim())
      .filter(Boolean)
    return updateGpsProvider(selectedId.value, {
      name: selected.value?.name ?? 'simulator',
      connection_method: 'simulator' as GpsConnectionMethod,
      is_enabled: selected.value?.is_enabled ?? true,
      poll_interval_seconds: Number(simPoll.value) || 60,
      settings: {
        base_latitude: Number(selected.value?.settings.base_latitude ?? 21.4225),
        base_longitude: Number(selected.value?.settings.base_longitude ?? 39.8262),
        radius_km: Number(simRadius.value) || 3,
        simulate_failure: simFailure.value,
        offline_device_ids: offline,
      },
    })
  },
  onSuccess: async () => {
    flash.value = t('gpsProviders.simulatorSaved')
    await queryClient.invalidateQueries({ queryKey: ['gps-providers'] })
  },
})

function lifecycleChip(row: GpsProvider) {
  const meta = providerLifecycleLabel(row)
  return { label: t(meta.key), class: gpsToneClass[meta.tone] }
}
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-4 p-4 md:p-5" dir="rtl">
    <AppPageHeader :title="t('gpsProviders.title')" :subtitle="t('gpsProviders.subtitle')">
      <template #actions>
        <button
          type="button"
          class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-white px-3 text-sm font-semibold"
          :disabled="providersQuery.isFetching.value"
          @click="providersQuery.refetch()"
        >
          <RefreshCw class="h-4 w-4" :class="providersQuery.isFetching.value ? 'animate-spin' : ''" />
          {{ t('gpsProviders.refresh') }}
        </button>
        <button
          v-if="canManage"
          type="button"
          class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand-primary px-3 text-sm font-semibold text-white"
          data-test="gps-add-provider"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('gpsProviders.add') }}
        </button>
      </template>
    </AppPageHeader>

    <p
      class="rounded-lg border border-amber-200/80 bg-amber-50/90 px-3 py-2 text-xs leading-relaxed text-amber-950"
      data-test="gps-g4-notice"
    >
      {{ t('gpsProviders.g4NoticeCompact') }}
    </p>

    <p v-if="flash" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900" data-test="gps-flash">
      {{ flash }}
    </p>

    <div class="grid gap-3 sm:grid-cols-3">
      <div class="rounded-xl border border-brand-border bg-white px-4 py-3">
        <p class="text-xs font-semibold text-brand-text-muted">{{ t('gpsProviders.kpiTotal') }}</p>
        <p class="mt-1 text-2xl font-bold text-brand-text" data-test="gps-kpi-total">{{ summary.total }}</p>
      </div>
      <div class="rounded-xl border border-brand-border bg-white px-4 py-3">
        <p class="text-xs font-semibold text-brand-text-muted">{{ t('gpsProviders.kpiEnabled') }}</p>
        <p class="mt-1 text-2xl font-bold text-brand-text" data-test="gps-kpi-enabled">{{ summary.enabled }}</p>
      </div>
      <div class="rounded-xl border border-brand-border bg-white px-4 py-3">
        <p class="text-xs font-semibold text-brand-text-muted">{{ t('gpsProviders.kpiVerified') }}</p>
        <p class="mt-1 text-2xl font-bold text-brand-text" data-test="gps-kpi-verified">{{ summary.verified }}</p>
        <p class="mt-1 text-[11px] text-brand-text-muted">{{ t('gpsProviders.kpiVerifiedHint') }}</p>
      </div>
    </div>

    <div v-if="providersQuery.isLoading.value" class="space-y-3" data-test="gps-providers-loading">
      <div v-for="n in 3" :key="n" class="h-20 animate-pulse rounded-xl bg-[var(--surface-subtle)]" />
    </div>
    <div
      v-else-if="providersQuery.isError.value"
      class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
    >
      <p>{{ t('gpsProviders.loadError') }}</p>
      <button type="button" class="mt-2 text-sm font-semibold underline" @click="providersQuery.refetch()">
        {{ t('gpsProviders.retry') }}
      </button>
    </div>
    <div
      v-else-if="!(providersQuery.data.value?.length)"
      class="rounded-2xl border border-dashed border-brand-border bg-white px-6 py-12 text-center"
      data-test="gps-providers-empty"
    >
      <p class="text-base font-bold text-brand-text">{{ t('gpsProviders.emptyTitle') }}</p>
      <p class="mx-auto mt-2 max-w-md text-sm text-brand-text-muted">{{ t('gpsProviders.empty') }}</p>
      <button
        v-if="canManage"
        type="button"
        class="mt-5 inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white"
        @click="openCreate"
      >
        <Plus class="h-4 w-4" />
        {{ t('gpsProviders.add') }}
      </button>
    </div>
    <div v-else class="grid gap-4 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1.2fr)]">
      <section class="space-y-2">
        <h2 class="text-sm font-bold text-brand-text">{{ t('gpsProviders.listTitle') }}</h2>
        <ul class="space-y-2" data-test="gps-provider-list">
          <li
            v-for="row in providersQuery.data.value"
            :key="row.id"
            class="cursor-pointer rounded-xl border bg-white px-3 py-3 transition"
            :class="
              selectedId === row.id
                ? 'border-brand-primary ring-1 ring-brand-primary/30'
                : 'border-brand-border hover:border-brand-primary/40'
            "
            @click="selectedId = row.id"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate font-semibold text-brand-text">{{ row.name }}</p>
                <p class="mt-0.5 text-xs text-brand-text-muted">
                  <span dir="ltr">{{ row.code }}</span>
                  · {{ t(providerMethodLabelKey(row.connection_method)) }}
                </p>
              </div>
              <span
                class="shrink-0 rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                :class="lifecycleChip(row).class"
                :data-test="`gps-health-${row.id}`"
              >
                {{ lifecycleChip(row).label }}
              </span>
            </div>
            <p class="mt-2 text-[11px] text-brand-text-muted">
              {{ t(providerHealthLabelKey(row.last_health_status)) }}
              <template v-if="row.last_sync_at"> · {{ row.last_sync_at }}</template>
            </p>
          </li>
        </ul>
      </section>

      <section v-if="selected" class="space-y-4 rounded-xl border border-brand-border bg-white p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-base font-bold text-brand-text">{{ selected.name }}</h2>
            <p class="mt-1 text-xs text-brand-text-muted">
              {{ t(providerMethodLabelKey(selected.connection_method)) }}
              ·
              <span :class="lifecycleChip(selected).class" class="rounded-full border px-2 py-0.5 font-semibold">
                {{ lifecycleChip(selected).label }}
              </span>
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              v-if="canManage"
              type="button"
              class="rounded-lg border border-brand-border px-3 py-1.5 text-sm font-semibold"
              @click="openEdit(selected)"
            >
              {{ t('gpsProviders.edit') }}
            </button>
            <button
              v-if="canManage"
              type="button"
              class="rounded-lg border border-brand-border px-3 py-1.5 text-sm font-semibold"
              :disabled="testMutation.isPending.value"
              @click="testMutation.mutate(selected.id)"
            >
              {{ t('gpsProviders.testConnection') }}
            </button>
            <button
              v-if="canManage"
              type="button"
              class="rounded-lg border border-brand-border px-3 py-1.5 text-sm font-semibold"
              :disabled="syncMutation.isPending.value"
              @click="syncMutation.mutate(selected.id)"
            >
              {{ t('gpsProviders.syncNow') }}
            </button>
            <button
              v-if="canManage"
              type="button"
              class="rounded-lg border border-red-300 px-3 py-1.5 text-sm font-semibold text-red-700"
              @click="onDelete(selected)"
            >
              {{ t('gpsProviders.delete') }}
            </button>
          </div>
        </div>

        <dl class="grid gap-3 sm:grid-cols-2 text-sm">
          <div class="rounded-lg bg-[var(--surface-subtle)] px-3 py-2">
            <dt class="text-xs text-brand-text-muted">{{ t('gpsProviders.enabled') }}</dt>
            <dd class="mt-0.5 font-semibold">{{ selected.is_enabled ? t('gpsProviders.yes') : t('gpsProviders.no') }}</dd>
          </div>
          <div class="rounded-lg bg-[var(--surface-subtle)] px-3 py-2">
            <dt class="text-xs text-brand-text-muted">{{ t('gpsProviders.credentials') }}</dt>
            <dd class="mt-0.5 font-semibold">
              {{ selected.has_credentials ? selected.credentials_masked : t('gpsProviders.none') }}
            </dd>
          </div>
          <div class="rounded-lg bg-[var(--surface-subtle)] px-3 py-2">
            <dt class="text-xs text-brand-text-muted">{{ t('gpsProviders.lastSync') }}</dt>
            <dd class="mt-0.5 font-semibold">{{ selected.last_sync_at ?? '—' }}</dd>
          </div>
          <div class="rounded-lg bg-[var(--surface-subtle)] px-3 py-2">
            <dt class="text-xs text-brand-text-muted">{{ t('gpsProviders.healthLabel') }}</dt>
            <dd class="mt-0.5 font-semibold">{{ t(providerHealthLabelKey(selected.last_health_status)) }}</dd>
          </div>
        </dl>

        <p v-if="selected.last_error_sanitized" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
          {{ selected.last_error_sanitized }}
        </p>

        <div
          v-if="selected.connection_method === 'simulator' && canManage"
          class="space-y-2 border-t border-brand-border pt-3"
          data-test="gps-simulator-controls"
        >
          <h3 class="text-sm font-bold">{{ t('gpsProviders.simulatorTitle') }}</h3>
          <p class="text-xs text-amber-800">{{ t('gpsProviders.simulatorHint') }}</p>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="simFailure" type="checkbox" data-test="gps-sim-failure" />
            {{ t('gpsProviders.simulateFailure') }}
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-muted">{{ t('gpsProviders.offlineDevices') }}</span>
            <input v-model="simOfflineDevices" class="w-full rounded-xl border px-3 py-2" data-test="gps-sim-offline" />
          </label>
          <div class="grid gap-2 md:grid-cols-2">
            <label class="block text-sm">
              <span class="mb-1 block text-brand-text-muted">{{ t('gpsProviders.radiusKm') }}</span>
              <input v-model="simRadius" type="number" min="0.1" step="0.1" class="w-full rounded-xl border px-3 py-2" />
            </label>
            <label class="block text-sm">
              <span class="mb-1 block text-brand-text-muted">{{ t('gpsProviders.pollSeconds') }}</span>
              <input v-model="simPoll" type="number" min="30" class="w-full rounded-xl border px-3 py-2" />
            </label>
          </div>
          <button
            type="button"
            class="rounded-xl border px-3 py-2 text-sm font-semibold"
            :disabled="simulatorMutation.isPending.value"
            @click="simulatorMutation.mutate()"
          >
            {{ t('gpsProviders.saveSimulator') }}
          </button>
        </div>

        <p class="border-t border-brand-border pt-3 text-xs text-brand-text-muted">
          {{ t('gpsProviders.devicesMovedHint') }}
        </p>
      </section>
    </div>

    <GpsProviderFormDrawer
      :open="formOpen"
      :editing="editing"
      :form="form"
      :submitting="saveMutation.isPending.value"
      :form-error="formError"
      @update:form="form = $event"
      @close="closeForm"
      @submit="saveMutation.mutate()"
    />
  </div>
</template>
