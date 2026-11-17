<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { listAssets } from '@/modules/assets/api/assetsApi'
import type { Asset } from '@/modules/assets/types/assets'
import { listContracts } from '@/modules/contracts/api/contractsApi'
import type { Contract } from '@/modules/contracts/types/contracts'
import { ApiError } from '@/shared/api/http'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import type { AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useFormDirty } from '@/shared/composables/useFormDirty'
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
const drawerRef = ref<InstanceType<typeof AppFormDrawer> | null>(null)
const mutation = useUpdateVehicleLinksMutation()
const submitting = computed(() => mutation.isPending.value)
const form = reactive<VehicleLinksFormState>(emptyVehicleLinksForm())
const formError = ref('')

const { data: vehicle, isLoading, isError, refetch } = useTransportVehicleQuery(
  computed(() => (props.open ? props.vehicleId : null)),
)

const { dirty, capture, reset: resetDirty } = useFormDirty(
  () => ({ asset_id: form.asset_id, contract_id: form.contract_id }),
  {
    enabled: () => props.open && !isLoading.value && vehicle.value != null,
  },
)

watch(
  () => [props.open, vehicle.value?.id, vehicle.value?.asset_id, vehicle.value?.contract_id] as const,
  () => {
    if (!props.open || !vehicle.value) return
    form.asset_id = vehicle.value.asset_id ?? ''
    form.contract_id = vehicle.value.contract_id ?? ''
    formError.value = ''
    capture()
  },
)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) return
    resetDirty()
    formError.value = ''
    Object.assign(form, emptyVehicleLinksForm())
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

const drawerDescription = computed(() => {
  if (!vehicle.value) return ''
  return `${vehicle.value.vehicle_number} · ${vehicle.value.plate_number}`
})

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

function onOpenUpdate(value: boolean): void {
  emit('update:open', value)
}

function requestDismiss(): void {
  void drawerRef.value?.requestClose()
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
    emit('update:open', false)
  } catch (error) {
    formError.value = apiMessage(error)
  }
}
</script>

<template>
  <AppFormDrawer
    ref="drawerRef"
    :open="open"
    :title="t('transportation.vehicles.linksTitle')"
    :description="drawerDescription"
    size="sm"
    :loading="isLoading"
    :dirty="dirty"
    :submitting="submitting"
    :submit-disabled="isError"
    :cancel-label="t('transportation.vehicles.cancel')"
    :submit-label="t('transportation.vehicles.linksSave')"
    @update:open="onOpenUpdate"
    @submit="submit"
  >
    <div v-if="isError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
      <p>{{ t('transportation.vehicles.errors.load') }}</p>
      <button type="button" class="mt-2 font-semibold underline" @click="() => refetch()">
        {{ t('transportation.vehicles.retry') }}
      </button>
    </div>
    <template v-else-if="vehicle">
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

    <template #footer>
      <button
        type="button"
        class="inline-flex h-11 flex-1 items-center justify-center rounded-xl border border-brand-border text-sm font-semibold disabled:opacity-50"
        :disabled="submitting"
        @click="requestDismiss"
      >
        {{ t('transportation.vehicles.cancel') }}
      </button>
      <button
        type="submit"
        class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-brand-primary-dark text-sm font-semibold text-white disabled:opacity-50"
        :disabled="submitting || isLoading || isError"
      >
        {{ t('transportation.vehicles.linksSave') }}
      </button>
    </template>
  </AppFormDrawer>
</template>