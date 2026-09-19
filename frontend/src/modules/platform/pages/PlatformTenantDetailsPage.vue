<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import {
  Archive,
  Loader2,
  PauseCircle,
  PlayCircle,
  Save,
} from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppPhoneInput from '@/shared/components/AppPhoneInput.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { listTimezones } from '@/modules/settings/validation/settingsValidation'

import TenantLifecycleReasonDialog from '../components/TenantLifecycleReasonDialog.vue'
import TenantStatusBadge from '../components/TenantStatusBadge.vue'
import {
  useActivatePlatformTenantMutation,
  useArchivePlatformTenantMutation,
  useSuspendPlatformTenantMutation,
  useTransferPlatformTenantOwnershipMutation,
  useUpdatePlatformTenantMutation,
} from '../mutations/usePlatformTenantMutations'
import { usePlatformTenantQuery } from '../queries/usePlatformTenantQuery'
import { usePlatformTenantUsersQuery } from '../queries/usePlatformTenantUsersQuery'
import { validateLifecycleReason } from '../validation/platformValidation'

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const { confirm } = useConfirm()
const { can } = usePermissions()

const tenantId = computed(() => Number(route.params.id))
const { data: tenant, isLoading, isError, refetch } = usePlatformTenantQuery(tenantId)
const { data: tenantUsers } = usePlatformTenantUsersQuery(tenantId)

const updateMutation = useUpdatePlatformTenantMutation()
const activateMutation = useActivatePlatformTenantMutation()
const suspendMutation = useSuspendPlatformTenantMutation()
const archiveMutation = useArchivePlatformTenantMutation()
const transferMutation = useTransferPlatformTenantOwnershipMutation()

const form = reactive({
  name: '',
  locale: 'ar',
  timezone: 'Asia/Riyadh',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  notes: '',
})

const fieldErrors = ref<Record<string, string>>({})
const formError = ref('')
const transferOwnerId = ref('')
const transferError = ref('')

const reasonOpen = ref(false)
const reasonKind = ref<'suspend' | 'archive' | null>(null)
const reason = ref('')
const reasonFieldError = ref('')
const reasonFormError = ref('')

watch(
  tenant,
  (value) => {
    if (!value) return
    form.name = value.name
    form.locale = value.locale || 'ar'
    form.timezone = value.timezone || 'Asia/Riyadh'
    form.contact_name = value.contact.name ?? ''
    form.contact_email = value.contact.email ?? ''
    form.contact_phone = value.contact.phone ?? ''
    form.notes = value.notes ?? ''
    fieldErrors.value = {}
    formError.value = ''
  },
  { immediate: true },
)

const localeOptions = computed<AppSelectOption[]>(() => [
  { value: 'ar', label: t('platform.locale.ar') },
  { value: 'en', label: t('platform.locale.en') },
])

const timezoneOptions = computed<AppSelectOption[]>(() =>
  listTimezones(form.timezone).map((tz) => ({ value: tz, label: tz })),
)

const lifecyclePending = computed(
  () =>
    activateMutation.isPending.value ||
    suspendMutation.isPending.value ||
    archiveMutation.isPending.value,
)
const saving = computed(() => updateMutation.isPending.value)
const transferring = computed(() => transferMutation.isPending.value)

const canEdit = computed(() => can('platform_tenants.update') && tenant.value?.status !== 'archived')
const canActivate = computed(
  () =>
    can('platform_tenants.activate') &&
    (tenant.value?.status === 'pending' || tenant.value?.status === 'suspended'),
)
const canSuspend = computed(
  () => can('platform_tenants.suspend') && tenant.value?.status === 'active',
)
const canArchive = computed(
  () => can('platform_tenants.archive') && tenant.value?.status !== 'archived',
)
const canTransfer = computed(
  () => can('platform_tenants.update') && tenant.value?.status !== 'archived',
)

const transferCandidates = computed(() =>
  (tenantUsers.value ?? []).filter((u) => !u.is_owner && u.status === 'active'),
)

const transferOwnerOptions = computed<AppSelectOption[]>(() =>
  transferCandidates.value.map((u) => ({
    value: String(u.id),
    label: `${u.name} — ${u.email}`,
  })),
)

const selectedTransferUser = computed(() => {
  const id = Number(transferOwnerId.value)
  return transferCandidates.value.find((u) => u.id === id) ?? null
})

function formatDate(value: string | null): string {
  if (!value) return '—'
  return new Date(value).toLocaleString('ar-SA')
}

function apiMessage(error: unknown): string {
  if (error instanceof ApiError) {
    return error.message || t('platform.errors.generic')
  }
  return t('platform.errors.generic')
}

async function saveEdits(): Promise<void> {
  if (!tenant.value || !canEdit.value) return
  fieldErrors.value = {}
  formError.value = ''

  if (!form.name.trim()) {
    fieldErrors.value.name = t('platform.validation.required')
    return
  }
  if (form.contact_email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.contact_email.trim())) {
    fieldErrors.value.contact_email = t('platform.validation.email')
    return
  }

  try {
    await updateMutation.mutateAsync({
      id: tenant.value.id,
      payload: {
        name: form.name.trim(),
        locale: form.locale,
        timezone: form.timezone,
        contact_name: form.contact_name.trim() || null,
        contact_email: form.contact_email.trim() || null,
        contact_phone: form.contact_phone.trim() || null,
        notes: form.notes.trim() || null,
      },
    })
    toast.success(t('platform.success.update'))
  } catch (error) {
    if (error instanceof ApiError && error.status === 422 && error.errors) {
      fieldErrors.value = Object.fromEntries(
        Object.entries(error.errors).map(([k, v]) => [k, v[0] ?? t('platform.errors.generic')]),
      )
      return
    }
    formError.value = apiMessage(error)
  }
}

async function onActivate(): Promise<void> {
  if (!tenant.value) return
  const ok = await confirm({
    title: t('platform.lifecycle.activateTitle'),
    message: t('platform.lifecycle.activateBody', { name: tenant.value.name }),
    confirmLabel: t('platform.actions.activate'),
    variant: 'primary',
  })
  if (!ok) return

  try {
    await activateMutation.mutateAsync(tenant.value.id)
    toast.success(t('platform.success.activate'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function openReason(kind: 'suspend' | 'archive'): void {
  reasonKind.value = kind
  reason.value = ''
  reasonFieldError.value = ''
  reasonFormError.value = ''
  reasonOpen.value = true
}

function closeReason(): void {
  reasonOpen.value = false
  reasonKind.value = null
}

async function submitReason(): Promise<void> {
  if (!tenant.value || !reasonKind.value) return
  reasonFieldError.value = ''
  reasonFormError.value = ''
  const validation = validateLifecycleReason(reason.value)
  if (validation.reason) {
    reasonFieldError.value = t(`platform.validation.${validation.reason}`)
    return
  }

  try {
    if (reasonKind.value === 'suspend') {
      await suspendMutation.mutateAsync({ id: tenant.value.id, reason: reason.value.trim() })
      toast.success(t('platform.success.suspend'))
    } else {
      await archiveMutation.mutateAsync({ id: tenant.value.id, reason: reason.value.trim() })
      toast.success(t('platform.success.archive'))
    }
    closeReason()
  } catch (error) {
    reasonFormError.value = apiMessage(error)
  }
}

async function onTransfer(): Promise<void> {
  if (!tenant.value || !canTransfer.value) return
  transferError.value = ''

  const id = Number(transferOwnerId.value)
  if (!Number.isInteger(id) || id <= 0 || !selectedTransferUser.value) {
    transferError.value = t('platform.validation.newOwnerId')
    return
  }

  const ok = await confirm({
    title: t('platform.transfer.confirmTitle'),
    message: t('platform.transfer.confirmBody', { name: selectedTransferUser.value.name }),
    confirmLabel: t('platform.transfer.submit'),
    variant: 'warning',
  })
  if (!ok) return

  try {
    await transferMutation.mutateAsync({ id: tenant.value.id, newOwnerId: id })
    transferOwnerId.value = ''
    toast.success(t('platform.success.transfer'))
  } catch (error) {
    transferError.value = apiMessage(error)
  }
}
</script>

<template>
  <div class="space-y-5">
    <AppPageHeader
      :title="tenant?.name ?? t('platform.details.title')"
      :subtitle="t('platform.details.subtitle')"
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'platform-tenants' }"
          class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold"
        >
          {{ t('platform.backToList') }}
        </RouterLink>
      </template>
    </AppPageHeader>

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface px-4 py-10 text-center text-sm text-brand-text-secondary"
    >
      {{ t('platform.details.loading') }}
    </div>

    <div
      v-else-if="isError || !tenant"
      class="rounded-2xl border border-red-200 bg-red-50 px-4 py-8 text-center"
    >
      <p class="text-sm text-red-800">{{ t('platform.details.loadError') }}</p>
      <button
        type="button"
        class="mt-3 inline-flex h-10 items-center rounded-xl bg-red-700 px-4 text-sm font-semibold text-white"
        @click="() => refetch()"
      >
        {{ t('platform.retry') }}
      </button>
    </div>

    <template v-else>
      <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface px-4 py-3">
        <TenantStatusBadge :status="tenant.status" />
        <span class="font-mono text-sm text-brand-text-muted" dir="ltr">{{ tenant.code }}</span>
        <span class="text-sm text-brand-text-muted">
          {{ t('platform.details.usersCount', { count: tenant.users_count }) }}
        </span>

        <div class="ms-auto flex flex-wrap gap-2">
          <button
            v-if="canActivate"
            type="button"
            class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-emerald-700 px-3 text-sm font-semibold text-white disabled:opacity-60"
            :disabled="lifecyclePending"
            @click="onActivate"
          >
            <PlayCircle class="h-4 w-4" />
            {{ t('platform.actions.activate') }}
          </button>
          <button
            v-if="canSuspend"
            type="button"
            class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-orange-700 px-3 text-sm font-semibold text-white disabled:opacity-60"
            :disabled="lifecyclePending"
            @click="openReason('suspend')"
          >
            <PauseCircle class="h-4 w-4" />
            {{ t('platform.actions.suspend') }}
          </button>
          <button
            v-if="canArchive"
            type="button"
            class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-brand-border px-3 text-sm font-medium text-brand-text-secondary disabled:opacity-60"
            :disabled="lifecyclePending"
            @click="openReason('archive')"
          >
            <Archive class="h-4 w-4" />
            {{ t('platform.actions.archive') }}
          </button>
        </div>
      </div>

      <div class="grid gap-5 lg:grid-cols-2">
        <!-- Editable fields -->
        <form class="space-y-4 rounded-2xl border border-brand-border bg-brand-surface p-5" @submit.prevent="saveEdits">
          <h2 class="text-base font-bold">{{ t('platform.details.editSection') }}</h2>

          <label class="block">
            <span class="text-sm font-medium">{{ t('platform.fields.name') }}</span>
            <input
              v-model="form.name"
              :disabled="!canEdit"
              class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm disabled:bg-brand-bg"
            />
            <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
          </label>

          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
              <span class="text-sm font-medium">{{ t('platform.fields.locale') }}</span>
              <div class="mt-1">
                <AppSelect v-model="form.locale" :options="localeOptions" :disabled="!canEdit" />
              </div>
            </label>
            <label class="block">
              <span class="text-sm font-medium">{{ t('platform.fields.timezone') }}</span>
              <div class="mt-1">
                <AppSelect
                  v-model="form.timezone"
                  :options="timezoneOptions"
                  searchable
                  :disabled="!canEdit"
                />
              </div>
            </label>
          </div>

          <label class="block">
            <span class="text-sm font-medium">{{ t('platform.fields.contactName') }}</span>
            <input
              v-model="form.contact_name"
              :disabled="!canEdit"
              class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm disabled:bg-brand-bg"
            />
          </label>

          <label class="block">
            <span class="text-sm font-medium">{{ t('platform.fields.contactEmail') }}</span>
            <input
              v-model="form.contact_email"
              type="email"
              dir="ltr"
              :disabled="!canEdit"
              class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm disabled:bg-brand-bg"
            />
            <p v-if="fieldErrors.contact_email" class="mt-1 text-xs text-red-600">
              {{ fieldErrors.contact_email }}
            </p>
          </label>

          <label class="block">
            <span class="text-sm font-medium">{{ t('platform.fields.contactPhone') }}</span>
            <AppPhoneInput
              class="mt-1"
              v-model="form.contact_phone"
              :disabled="!canEdit"
            />
          </label>

          <label class="block">
            <span class="text-sm font-medium">{{ t('platform.fields.notes') }}</span>
            <textarea
              v-model="form.notes"
              rows="3"
              :disabled="!canEdit"
              class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm disabled:bg-brand-bg"
            />
          </label>

          <p
            v-if="formError"
            class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
          >
            {{ formError }}
          </p>

          <PermissionGuard permission="platform_tenants.update">
            <button
              type="submit"
              class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary px-4 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="!canEdit || saving"
            >
              <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
              <Save v-else class="h-4 w-4" />
              {{ t('platform.actions.save') }}
            </button>
          </PermissionGuard>
        </form>

        <!-- Read-only + ownership -->
        <div class="space-y-5">
          <section class="space-y-3 rounded-2xl border border-brand-border bg-brand-surface p-5">
            <h2 class="text-base font-bold">{{ t('platform.details.infoSection') }}</h2>
            <dl class="space-y-3 text-sm">
              <div class="flex justify-between gap-3">
                <dt class="text-brand-text-muted">{{ t('platform.fields.code') }}</dt>
                <dd class="font-mono" dir="ltr">{{ tenant.code }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-brand-text-muted">{{ t('platform.details.createdAt') }}</dt>
                <dd>{{ formatDate(tenant.created_at) }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-brand-text-muted">{{ t('platform.details.updatedAt') }}</dt>
                <dd>{{ formatDate(tenant.updated_at) }}</dd>
              </div>
              <div v-if="tenant.suspended_at" class="flex justify-between gap-3">
                <dt class="text-brand-text-muted">{{ t('platform.details.suspendedAt') }}</dt>
                <dd>{{ formatDate(tenant.suspended_at) }}</dd>
              </div>
              <div v-if="tenant.archived_at" class="flex justify-between gap-3">
                <dt class="text-brand-text-muted">{{ t('platform.details.archivedAt') }}</dt>
                <dd>{{ formatDate(tenant.archived_at) }}</dd>
              </div>
            </dl>
          </section>

          <section class="space-y-4 rounded-2xl border border-brand-border bg-brand-surface p-5">
            <h2 class="text-base font-bold">{{ t('platform.transfer.title') }}</h2>

            <div v-if="tenant.owner" class="rounded-xl bg-brand-bg/60 px-4 py-3">
              <p class="text-xs text-brand-text-muted">{{ t('platform.transfer.currentOwner') }}</p>
              <p class="mt-1 font-semibold">{{ tenant.owner.name }}</p>
              <p class="text-sm text-brand-text-muted" dir="ltr">{{ tenant.owner.email }}</p>
              <p class="mt-1 text-xs text-brand-text-muted" dir="ltr">
                ID: {{ tenant.owner.id }}
              </p>
            </div>
            <p v-else class="text-sm text-brand-text-muted">{{ t('platform.transfer.noOwner') }}</p>

            <PermissionGuard permission="platform_tenants.update">
              <div v-if="canTransfer" class="space-y-3">
                <p class="text-sm text-brand-text-secondary">{{ t('platform.transfer.hint') }}</p>
                <p
                  v-if="transferCandidates.length === 0"
                  class="text-sm text-brand-text-muted"
                >
                  {{ t('platform.transfer.noCandidates') }}
                </p>
                <template v-else>
                  <label class="block">
                    <span class="text-sm font-medium">{{ t('platform.transfer.newOwnerId') }}</span>
                    <div class="mt-1">
                      <AppSelect
                        v-model="transferOwnerId"
                        :options="transferOwnerOptions"
                        searchable
                        :placeholder="t('platform.transfer.newOwnerIdPlaceholder')"
                      />
                    </div>
                  </label>
                  <p
                    v-if="transferError"
                    class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
                  >
                    {{ transferError }}
                  </p>
                  <button
                    type="button"
                    class="inline-flex h-11 items-center justify-center rounded-xl bg-brand-primary px-4 text-sm font-semibold text-white disabled:opacity-60"
                    :disabled="transferring || !transferOwnerId"
                    @click="onTransfer"
                  >
                    <Loader2
                      v-if="transferring"
                      class="me-2 h-4 w-4 animate-spin"
                    />
                    {{ t('platform.transfer.submit') }}
                  </button>
                </template>
              </div>
            </PermissionGuard>
          </section>
        </div>
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
