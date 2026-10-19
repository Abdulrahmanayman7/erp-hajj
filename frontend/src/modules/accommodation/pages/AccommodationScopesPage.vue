<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { ChevronLeft, ChevronRight, Plus } from 'lucide-vue-next'

import { listUsers } from '@/modules/users/api/usersApi'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { toSelectId } from '@/shared/lookups/selectOptions'

import AccommodationSeasonBar from '../components/AccommodationSeasonBar.vue'
import AccommodationSubnav from '../components/AccommodationSubnav.vue'
import { useAccommodationErrors } from '../composables/useAccommodationErrors'
import {
  useGrantFacilityScopeMutation,
  useGrantSupervisorScopeMutation,
  useRevokeFacilityScopeMutation,
  useRevokeSupervisorScopeMutation,
} from '../mutations/useAccommodationMutations'
import { useFacilitiesQuery, useFacilityScopesQuery, useSupervisorScopesQuery } from '../queries/useAccommodationQueries'
import { useAccommodationSeasonStore } from '../stores/seasonContextStore'
import type { AccommodationFacilityScope, AccommodationSupervisorScope, FacilityScopeFormState, SupervisorScopeFormState } from '../types/accommodation'
import { ACCOMMODATION_READ_PERMISSIONS } from '../types/accommodation'
import {
  resolveListState,
  validateFacilityScopeForm,
  validateSupervisorScopeForm,
} from '../validation/accommodationValidation'

type GrantDrawer = 'facility' | 'supervisor' | null

const { t } = useI18n()
const { can, canAny } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()
const { message } = useAccommodationErrors()
const seasonStore = useAccommodationSeasonStore()
const { canWrite } = storeToRefs(seasonStore)

const canManage = computed(() => can('accommodation.manage_scopes'))
const canReadFacilities = computed(() => canAny([...ACCOMMODATION_READ_PERMISSIONS]))

const fsPage = ref(1)
const ssPage = ref(1)

const { data: fsData, isLoading: fsLoading, isError: fsError, refetch: refetchFs } = useFacilityScopesQuery(
  fsPage,
  canManage,
)
const { data: ssData, isLoading: ssLoading, isError: ssError, refetch: refetchSs } = useSupervisorScopesQuery(
  ssPage,
  canManage,
)

const facilityScopes = computed(() => fsData.value?.data ?? [])
const supervisorScopes = computed(() => ssData.value?.data ?? [])

const activeFacilityScopes = computed(() => facilityScopes.value.filter((row) => !row.revoked_at))
const revokedFacilityScopes = computed(() => facilityScopes.value.filter((row) => row.revoked_at))
const activeSupervisorScopes = computed(() => supervisorScopes.value.filter((row) => !row.revoked_at))
const revokedSupervisorScopes = computed(() => supervisorScopes.value.filter((row) => row.revoked_at))

const fsListState = computed(() =>
  resolveListState({ isLoading: fsLoading.value, isError: fsError.value, count: facilityScopes.value.length }),
)
const ssListState = computed(() =>
  resolveListState({ isLoading: ssLoading.value, isError: ssError.value, count: supervisorScopes.value.length }),
)

const { data: facilitiesData } = useFacilitiesQuery(ref(1), canReadFacilities)
const facilityOptions = computed<AppSelectOption[]>(() =>
  (facilitiesData.value?.data ?? []).map((row) => ({
    value: row.id,
    label: `${row.name} (${row.code})`,
  })),
)

function userSelectOption(user: { id: number; name: string; email: string }): AppSelectOption {
  return { value: user.id, label: user.name, hint: user.email }
}

const grantDrawer = ref<GrantDrawer>(null)
const formError = ref('')
const facilityScopeForm = reactive<FacilityScopeFormState>({ user_id: '', facility_id: '' })
const supervisorScopeForm = reactive<SupervisorScopeFormState>({ user_id: '' })

const grantFacilityMutation = useGrantFacilityScopeMutation()
const revokeFacilityMutation = useRevokeFacilityScopeMutation()
const grantSupervisorMutation = useGrantSupervisorScopeMutation()
const revokeSupervisorMutation = useRevokeSupervisorScopeMutation()

function closeDrawer(): void {
  grantDrawer.value = null
  formError.value = ''
}

function openFacilityGrant(): void {
  Object.assign(facilityScopeForm, { user_id: '', facility_id: '' })
  formError.value = ''
  grantDrawer.value = 'facility'
}

function openSupervisorGrant(): void {
  Object.assign(supervisorScopeForm, { user_id: '' })
  formError.value = ''
  grantDrawer.value = 'supervisor'
}

async function submitFacilityGrant(): Promise<void> {
  const errors = validateFacilityScopeForm(facilityScopeForm)
  if (Object.keys(errors).length) {
    formError.value = t('accommodation.errors.validation')
    return
  }
  try {
    await grantFacilityMutation.mutateAsync({
      user_id: Number(facilityScopeForm.user_id),
      facility_id: Number(facilityScopeForm.facility_id),
    })
    toast.success(t('accommodation.scopes.facilityGranted'))
    closeDrawer()
  } catch (error) {
    formError.value = message(error)
  }
}

async function submitSupervisorGrant(): Promise<void> {
  const errors = validateSupervisorScopeForm(supervisorScopeForm)
  if (Object.keys(errors).length) {
    formError.value = t('accommodation.errors.validation')
    return
  }
  try {
    await grantSupervisorMutation.mutateAsync({ user_id: Number(supervisorScopeForm.user_id) })
    toast.success(t('accommodation.scopes.supervisorGranted'))
    closeDrawer()
  } catch (error) {
    formError.value = message(error)
  }
}

async function revokeFacility(row: AccommodationFacilityScope): Promise<void> {
  if (!canWrite.value) return
  const ok = await confirm({
    title: t('accommodation.scopes.confirmRevoke.title'),
    message: t('accommodation.scopes.confirmRevoke.body'),
    confirmLabel: t('accommodation.scopes.revoke'),
    variant: 'warning',
  })
  if (!ok) return
  try {
    await revokeFacilityMutation.mutateAsync(row.id)
    toast.success(t('accommodation.scopes.revoked'))
  } catch (error) {
    toast.error(message(error))
  }
}

async function revokeSupervisor(row: AccommodationSupervisorScope): Promise<void> {
  if (!canWrite.value) return
  const ok = await confirm({
    title: t('accommodation.scopes.confirmRevoke.title'),
    message: t('accommodation.scopes.confirmRevoke.body'),
    confirmLabel: t('accommodation.scopes.revoke'),
    variant: 'warning',
  })
  if (!ok) return
  try {
    await revokeSupervisorMutation.mutateAsync(row.id)
    toast.success(t('accommodation.scopes.revoked'))
  } catch (error) {
    toast.error(message(error))
  }
}
</script>

<template>
  <div class="space-y-5">
    <AppPageHeader :title="t('accommodation.scopes.title')" :subtitle="t('accommodation.scopes.subtitle')" />

    <AccommodationSubnav />
    <AccommodationSeasonBar />

    <div v-if="!canManage" class="rounded-xl border border-dashed px-4 py-10 text-center text-sm">
      {{ t('accommodation.errors.forbidden') }}
    </div>

    <template v-else>
      <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-bold">{{ t('accommodation.scopes.facilityScopes') }}</h2>
          <PermissionGuard permission="accommodation.manage_scopes">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-lg bg-brand-primary px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="!canWrite"
              @click="openFacilityGrant"
            >
              <Plus class="h-4 w-4" />
              {{ t('accommodation.scopes.grantFacility') }}
            </button>
          </PermissionGuard>
        </div>

        <div v-if="fsListState === 'loading'" class="rounded-xl border px-4 py-8 text-center text-sm">{{ t('accommodation.loading') }}</div>
        <div v-else-if="fsListState === 'error'" class="rounded-xl border border-red-200 bg-red-50 px-4 py-8 text-center">
          <button type="button" class="underline" @click="() => refetchFs()">{{ t('accommodation.retry') }}</button>
        </div>
        <template v-else>
          <div class="overflow-hidden rounded-xl border">
            <table class="min-w-full text-sm">
              <thead class="bg-brand-bg text-brand-text-secondary">
                <tr>
                  <th class="px-4 py-3 text-start">{{ t('accommodation.fields.userId') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('accommodation.fields.facilityId') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('accommodation.fields.actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="activeFacilityScopes.length === 0" class="border-t">
                  <td colspan="3" class="px-4 py-6 text-center text-brand-text-muted">{{ t('accommodation.scopes.noActiveFacility') }}</td>
                </tr>
                <tr v-for="row in activeFacilityScopes" :key="row.id" class="border-t">
                  <td class="px-4 py-3">{{ row.user_id }}</td>
                  <td class="px-4 py-3">{{ row.facility_id }}</td>
                  <td class="px-4 py-3">
                    <button type="button" class="text-sm font-semibold text-amber-900 disabled:opacity-50" :disabled="!canWrite" @click="revokeFacility(row)">
                      {{ t('accommodation.scopes.revoke') }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="revokedFacilityScopes.length" class="space-y-2">
            <h3 class="text-sm font-semibold text-brand-text-secondary">{{ t('accommodation.scopes.revokedHistory') }}</h3>
            <div class="overflow-hidden rounded-xl border border-dashed">
              <table class="min-w-full text-sm">
                <thead class="bg-brand-bg/60">
                  <tr>
                    <th class="px-4 py-2 text-start">{{ t('accommodation.fields.userId') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('accommodation.fields.facilityId') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('accommodation.fields.revokedAt') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in revokedFacilityScopes" :key="`revoked-fs-${row.id}`" class="border-t">
                    <td class="px-4 py-2">{{ row.user_id }}</td>
                    <td class="px-4 py-2">{{ row.facility_id }}</td>
                    <td class="px-4 py-2 text-brand-text-muted">{{ row.revoked_at }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div v-if="fsData?.meta" class="flex justify-between text-sm">
            <span>{{ t('accommodation.pagination.page', { page: fsData.meta.current_page, last: fsData.meta.last_page }) }}</span>
            <div class="flex gap-1">
              <button type="button" class="rounded border px-2 disabled:opacity-40" :disabled="fsPage <= 1" @click="fsPage -= 1"><ChevronRight class="h-4 w-4" /></button>
              <button type="button" class="rounded border px-2 disabled:opacity-40" :disabled="fsPage >= fsData.meta.last_page" @click="fsPage += 1"><ChevronLeft class="h-4 w-4" /></button>
            </div>
          </div>
        </template>
      </section>

      <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-bold">{{ t('accommodation.scopes.supervisorScopes') }}</h2>
          <PermissionGuard permission="accommodation.manage_scopes">
            <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-brand-primary px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="!canWrite" @click="openSupervisorGrant">
              <Plus class="h-4 w-4" />{{ t('accommodation.scopes.grantSupervisor') }}
            </button>
          </PermissionGuard>
        </div>

        <div v-if="ssListState === 'loading'" class="rounded-xl border px-4 py-8 text-center text-sm">{{ t('accommodation.loading') }}</div>
        <div v-else-if="ssListState === 'error'" class="rounded-xl border border-red-200 bg-red-50 px-4 py-8 text-center">
          <button type="button" class="underline" @click="() => refetchSs()">{{ t('accommodation.retry') }}</button>
        </div>
        <template v-else>
          <div class="overflow-hidden rounded-xl border">
            <table class="min-w-full text-sm">
              <thead class="bg-brand-bg">
                <tr>
                  <th class="px-4 py-3 text-start">{{ t('accommodation.fields.userId') }}</th>
                  <th class="px-4 py-3 text-start">{{ t('accommodation.fields.actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="activeSupervisorScopes.length === 0" class="border-t">
                  <td colspan="2" class="px-4 py-6 text-center text-brand-text-muted">{{ t('accommodation.scopes.noActiveSupervisor') }}</td>
                </tr>
                <tr v-for="row in activeSupervisorScopes" :key="row.id" class="border-t">
                  <td class="px-4 py-3">{{ row.user_id }}</td>
                  <td class="px-4 py-3">
                    <button type="button" class="text-sm font-semibold text-amber-900 disabled:opacity-50" :disabled="!canWrite" @click="revokeSupervisor(row)">
                      {{ t('accommodation.scopes.revoke') }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="revokedSupervisorScopes.length" class="space-y-2">
            <h3 class="text-sm font-semibold text-brand-text-secondary">{{ t('accommodation.scopes.revokedHistory') }}</h3>
            <div class="overflow-hidden rounded-xl border border-dashed">
              <table class="min-w-full text-sm">
                <thead class="bg-brand-bg/60">
                  <tr>
                    <th class="px-4 py-2 text-start">{{ t('accommodation.fields.userId') }}</th>
                    <th class="px-4 py-2 text-start">{{ t('accommodation.fields.revokedAt') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in revokedSupervisorScopes" :key="`revoked-ss-${row.id}`" class="border-t">
                    <td class="px-4 py-2">{{ row.user_id }}</td>
                    <td class="px-4 py-2 text-brand-text-muted">{{ row.revoked_at }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div v-if="ssData?.meta" class="flex justify-between text-sm">
            <span>{{ t('accommodation.pagination.page', { page: ssData.meta.current_page, last: ssData.meta.last_page }) }}</span>
            <div class="flex gap-1">
              <button type="button" class="rounded border px-2 disabled:opacity-40" :disabled="ssPage <= 1" @click="ssPage -= 1"><ChevronRight class="h-4 w-4" /></button>
              <button type="button" class="rounded border px-2 disabled:opacity-40" :disabled="ssPage >= ssData.meta.last_page" @click="ssPage += 1"><ChevronLeft class="h-4 w-4" /></button>
            </div>
          </div>
        </template>
      </section>
    </template>

    <Teleport to="body">
      <div v-if="grantDrawer" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/30" @click="closeDrawer" />
        <aside class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[480px] flex-col bg-brand-surface shadow-xl">
          <header class="border-b px-5 py-4">
            <h3 class="text-lg font-bold">
              {{ grantDrawer === 'facility' ? t('accommodation.scopes.grantFacility') : t('accommodation.scopes.grantSupervisor') }}
            </h3>
          </header>
          <form class="flex flex-1 flex-col" @submit.prevent="grantDrawer === 'facility' ? submitFacilityGrant() : submitSupervisorGrant()">
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
              <label class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.user') }}</span>
                <AppRemoteSelect
                  v-if="can('users.view')"
                  :model-value="grantDrawer === 'facility' ? (facilityScopeForm.user_id === '' ? null : facilityScopeForm.user_id) : (supervisorScopeForm.user_id === '' ? null : supervisorScopeForm.user_id)"
                  query-key="accommodation-scope-users"
                  :fetcher="listUsers"
                  :map-option="userSelectOption"
                  @update:model-value="
                    grantDrawer === 'facility'
                      ? (facilityScopeForm.user_id = toSelectId($event))
                      : (supervisorScopeForm.user_id = toSelectId($event))
                  "
                />
                <input
                  v-else-if="grantDrawer === 'facility'"
                  v-model.number="facilityScopeForm.user_id"
                  type="number"
                  min="1"
                  class="w-full rounded-lg border px-3 py-2"
                >
                <input
                  v-else
                  v-model.number="supervisorScopeForm.user_id"
                  type="number"
                  min="1"
                  class="w-full rounded-lg border px-3 py-2"
                >
              </label>
              <label v-if="grantDrawer === 'facility'" class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.facility') }}</span>
                <AppSelect
                  v-if="canReadFacilities && facilityOptions.length"
                  :model-value="facilityScopeForm.facility_id === '' ? '' : facilityScopeForm.facility_id"
                  :options="facilityOptions"
                  @update:model-value="facilityScopeForm.facility_id = toSelectId($event)"
                />
                <input
                  v-else
                  v-model.number="facilityScopeForm.facility_id"
                  type="number"
                  min="1"
                  class="w-full rounded-lg border px-3 py-2"
                >
              </label>
              <p v-if="formError" class="text-sm text-red-700">{{ formError }}</p>
            </div>
            <footer class="flex justify-end gap-2 border-t px-5 py-4">
              <button type="button" class="rounded-lg px-3 py-2 text-sm" @click="closeDrawer">{{ t('accommodation.cancel') }}</button>
              <button type="submit" class="rounded-lg bg-brand-primary px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="!canWrite">
                {{ t('accommodation.scopes.grant') }}
              </button>
            </footer>
          </form>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
