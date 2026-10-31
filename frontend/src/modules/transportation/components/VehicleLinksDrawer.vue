<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { listAssets } from '@/modules/assets/api/assetsApi'
import type { Asset } from '@/modules/assets/types/assets'
import { listContracts } from '@/modules/contracts/api/contractsApi'
import type { Contract } from '@/modules/contracts/types/contracts'
import { ApiError } from '@/shared/api/http'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import type { AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useToast } from '@/shared/composables/useToast'
import { toSelectNullableId } from '@/shared/lookups/selectOptions'

import { useUpdateVehicleLinksMutation } from '../mutations/useFleetMutations'
import { useTransportVehicleQuery } from '../queries/useFleetQueries'
import type { TransportVehicle, VehicleLinksFormState } from '../types/fleet'
import { emptyVehicleLinksForm, mapTransportErrorCode } from '../validation/fleetValidation'

const props = defineProps<{
  open: boolean
  vehicleId: number | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  saved: [vehicle: TransportVehicle]
}>()

const { t } = useI18n()
const toast = useToast()
const mutation = useUpdateVehicleLinksMutation()
const submitting = computed(() => mutation.isPending.value)
const form = reactive<VehicleLinksFormState>(emptyVehicleLinksForm())
const formError = ref('')

const { data: vehicle, isLoading, isError, refetch } = useTransportVehicleQuery(
  computed(() => (props.open ? props.vehicleId : null)),
)

watch(
  () => [props.open, vehicle.value?.id, vehicle.value?.asset_id, vehicle.value?.contract_id] as const,
  () => {
    if (!props.open || !vehicle.value) return
    form.asset_id = vehicle.value.asset_id ?? ''
    form.contract_id = vehicle.value.contract_id ?? ''
    formError.value = ''
  },
)

const assetSelected = computed<AppSelectOption | null>(() => {
  const asset = vehicle.value?.asset
  if (!asset || form.asset_id !== asset.id) return null
  return { value: asset.id, label: asset.name, hint: asset.asset_number }
})

const contractSelected = computed<AppSelectOption | null>(() => {
  const contract = vehicle.value?.contract
  if (!contract || form.contract_id !== contract.id) return null
  return { value: contract.id, label: contract.title, hint: contract.contract_number }
})

const emptyAsset = computed<AppSelectOption>(() => ({
  value: '',
  label: t('transportation.vehicles.fields.noAsset'),
}))
const emptyContract = computed<AppSelectOption>(() => ({
  value: '',
  label: t('transportation.vehicles.fields.noContract'),
}))

const fetchAssets = (params: { search?: string; page: number; per_page: number }) =>
  listAssets(params)
const fetchContracts = (params: { search?: string; page: number; per_page: number }) =>
  listContracts(params)

function mapAsset(row: Asset): AppSelectOption {
  return { value: row.id, label: row.name, hint: row.asset_number }
}
function mapContract(row: Contract): AppSelectOption {
  return { value: row.id, label: row.title, hint: row.contract_number }
}

function close(): void {
  emit('update:open', false)
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('transportation.vehicles.errors.generic')
  const mapped = mapTransportErrorCode(error.code)
  if (mapped === 'assetTaken' || mapped === 'linkInvalid') {
    return t(`transportation.vehicles.errors.${mapped}`)
  }
  return error.message || t('transportation.vehicles.errors.generic')
}

async function submit(): Promise<void> {
  if (!props.vehicleId) return
  formError.value = ''
  try {
    const updated = await mutation.mutateAsync({
      id: props.vehicleId,
      payload: {
        asset_id: toSelectNullableId(form.asset_id),
        contract_id: toSelectNullableId(form.contract_id),
      },
    })
    toast.success(t('transportation.vehicles.toasts.linksUpdated'))
    emit('saved', updated)
    close()
  } catch (error) {
    formError.value = apiMessage(error)
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-[rgba(15,23,20,0.32)]" @click="close" />
      <aside
        class="absolute inset-y-0 start-0 flex h-dvh w-full max-w-[440px] flex-col bg-brand-surface"
        role="dialog"
        aria-modal="true"
      >
        <header class="border-b border-brand-border px-5 py-4">
          <h3 class="text-lg font-bold">{{ t('transportation.vehicles.linksTitle') }}</h3>
          <p v-if="vehicle" class="mt-1 text-xs text-brand-text-muted" dir="ltr">
            {{ vehicle.vehicle_number }} · {{ vehicle.plate_number }}
          </p>
        </header>

        <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
          <p v-if="isLoading" class="text-sm text-brand-text-muted">
            {{ t('transportation.vehicles.loading') }}
          </p>
          <div v-else-if="isError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
            <p>{{ t('transportation.vehicles.errors.load') }}</p>
            <button type="button" class="mt-2 font-semibold underline" @click="() => refetch()">
              {{ t('transportation.vehicles.retry') }}
            </button>
          </div>
          <template v-else>
            <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ formError }}
            </p>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.vehicles.fields.asset')
              }}</label>
              <AppRemoteSelect
                v-model="form.asset_id"
                :query-key="['transport-vehicle-assets']"
                :fetcher="fetchAssets"
                :map-option="mapAsset"
                :empty-option="emptyAsset"
                :selected-option="assetSelected"
                clearable
                searchable
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.vehicles.fields.contract')
              }}</label>
              <AppRemoteSelect
                v-model="form.contract_id"
                :query-key="['transport-vehicle-contracts']"
                :fetcher="fetchContracts"
                :map-option="mapContract"
                :empty-option="emptyContract"
                :selected-option="contractSelected"
                clearable
                searchable
              />
            </div>
          </template>
        </div>

        <footer class="flex gap-2 border-t border-brand-border px-5 py-4">
          <button
            type="button"
            class="h-11 flex-1 rounded-xl border border-brand-border text-sm font-semibold"
            @click="close"
          >
            {{ t('transportation.vehicles.cancel') }}
          </button>
          <button
            type="button"
            class="h-11 flex-1 rounded-xl bg-brand-primary-dark text-sm font-semibold text-white disabled:opacity-50"
            :disabled="submitting || isLoading || isError"
            @click="submit"
          >
            {{ t('transportation.vehicles.linksSave') }}
          </button>
        </footer>
      </aside>
    </div>
  </Teleport>
</template>
