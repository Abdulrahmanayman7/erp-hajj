<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, Pencil } from 'lucide-vue-next'

import EntityDocumentsSection from '@/modules/documents/components/EntityDocumentsSection.vue'
import { useEmployeesQuery } from '@/modules/employees/queries/useEmployeesQuery'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { ApiError } from '@/shared/api/http'
import type { AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import WarehouseFormDrawer from '../components/WarehouseFormDrawer.vue'
import { useUpdateWarehouseMutation } from '../mutations/useWarehouseMutations'
import { useInventoryBalancesQuery } from '../queries/useInventoryBalancesQuery'
import { useInventoryMovementsQuery } from '../queries/useMovementsQuery'
import { useWarehouseQuery } from '../queries/useWarehousesQuery'
import type { WarehouseFormState } from '../types/warehouses'
import {
  computeWarehouseStockKpis,
  formatQuantity,
  formatSignedQuantity,
  mapInventoryErrorCode,
  movementTypeBadgeClass,
  stockStateBadgeClass,
  validateWarehouseForm,
} from '../validation/inventoryValidation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()

const id = computed(() => Number(route.params.id))
const { data, isLoading, isError, refetch } = useWarehouseQuery(id)
const warehouse = computed(() => data.value ?? null)

const balancesParams = computed(() => ({
  warehouse_id: id.value,
  per_page: 100,
}))
const movementsParams = computed(() => ({
  warehouse_id: id.value,
  per_page: 10,
}))

const { data: balancesData } = useInventoryBalancesQuery(balancesParams)
const { data: movementsData } = useInventoryMovementsQuery(movementsParams)

const balances = computed(() => balancesData.value?.data ?? [])
const movements = computed(() => movementsData.value?.data ?? [])
const kpis = computed(() => computeWarehouseStockKpis(balances.value))

const { data: orgUnitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })
const { data: employeesData } = useEmployeesQuery(
  computed(() => ({ status: 'active' as const, per_page: 100 })),
)

const orgUnitFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.noOrgUnit') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({ value: u.id, label: u.name })),
])
const employeeFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.noEmployee') },
  ...(employeesData.value?.data ?? []).map((e) => ({ value: e.id, label: e.full_name })),
])

const updateMutation = useUpdateWarehouseMutation()
const drawerOpen = ref(false)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<WarehouseFormState>({
  name: '',
  description: '',
  location: '',
  organization_unit_id: '',
  responsible_employee_id: '',
  is_active: true,
  notes: '',
})

function openEdit(): void {
  if (!warehouse.value) return
  Object.assign(form, {
    name: warehouse.value.name,
    description: warehouse.value.description ?? '',
    location: warehouse.value.location ?? '',
    organization_unit_id: warehouse.value.organization_unit?.id ?? '',
    responsible_employee_id: warehouse.value.responsible_employee?.id ?? '',
    is_active: warehouse.value.is_active,
    notes: warehouse.value.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

async function submitForm(): Promise<void> {
  if (!warehouse.value) return
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  Object.assign(fieldErrors, validateWarehouseForm(form))
  if (Object.keys(fieldErrors).length) return
  try {
    await updateMutation.mutateAsync({
      id: warehouse.value.id,
      payload: {
        name: form.name.trim(),
        description: form.description.trim() || null,
        location: form.location.trim() || null,
        organization_unit_id: form.organization_unit_id === '' ? null : Number(form.organization_unit_id),
        responsible_employee_id:
          form.responsible_employee_id === '' ? null : Number(form.responsible_employee_id),
        is_active: form.is_active,
        notes: form.notes.trim() || null,
      },
    })
    toast.success(t('inventory.toasts.warehouseUpdated'))
    drawerOpen.value = false
  } catch (error) {
    if (error instanceof ApiError) {
      const mapped = mapInventoryErrorCode(error.code)
      formError.value =
        mapped !== 'generic' ? t(`inventory.errors.${mapped}`) : error.message || t('inventory.errors.generic')
    } else {
      formError.value = t('inventory.errors.generic')
    }
  }
}
</script>

<template>
  <div class="space-y-6">
    <button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="router.push('/app/warehouses')">
      <ArrowRight class="inline h-4 w-4" />
      {{ t('inventory.warehouses.backToList') }}
    </button>

    <div v-if="isLoading" class="rounded-2xl border p-10 text-center">{{ t('inventory.loadingDetails') }}</div>
    <div v-else-if="isError || !warehouse" class="rounded-2xl border p-10 text-center">
      {{ t('inventory.errors.loadDetails') }}
      <button type="button" class="ms-2 underline" @click="() => refetch()">{{ t('inventory.retry') }}</button>
    </div>

    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-sm">{{ warehouse.warehouse_number }}</span>
            <span
              class="rounded-full px-2 py-0.5 text-xs font-semibold"
              :class="warehouse.is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-neutral-100 text-neutral-600'"
            >
              {{ warehouse.is_active ? t('inventory.status.active') : t('inventory.status.inactive') }}
            </span>
          </div>
          <h2 class="mt-2 text-2xl font-bold">{{ warehouse.name }}</h2>
        </div>
        <PermissionGuard v-if="can('warehouses.update')" permission="warehouses.update">
          <button type="button" class="rounded-xl border px-4 py-2 text-sm font-semibold" @click="openEdit">
            <Pencil class="inline h-4 w-4" />
            {{ t('inventory.actions.edit') }}
          </button>
        </PermissionGuard>
      </div>

      <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
          <p class="text-xs text-brand-text-muted">{{ t('inventory.kpis.distinctItems') }}</p>
          <p class="mt-1 text-2xl font-bold">{{ kpis.distinctItems }}</p>
        </div>
        <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
          <p class="text-xs text-brand-text-muted">{{ t('inventory.kpis.lowStock') }}</p>
          <p class="mt-1 text-2xl font-bold text-amber-800">{{ kpis.lowStock }}</p>
        </div>
        <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
          <p class="text-xs text-brand-text-muted">{{ t('inventory.kpis.outOfStock') }}</p>
          <p class="mt-1 text-2xl font-bold text-red-700">{{ kpis.outOfStock }}</p>
        </div>
      </div>

      <section>
        <h3 class="mb-3 font-bold">{{ t('inventory.sections.overview') }}</h3>
        <div class="grid gap-4 md:grid-cols-3">
          <div
            v-for="item in [
              { label: 'location', value: warehouse.location },
              { label: 'organizationUnit', value: warehouse.organization_unit?.name },
              { label: 'responsibleEmployee', value: warehouse.responsible_employee?.full_name },
              { label: 'createdBy', value: warehouse.created_by?.name },
            ]"
            :key="item.label"
            class="rounded-2xl border border-brand-border bg-brand-surface p-4"
          >
            <p class="text-xs text-brand-text-muted">{{ t(`inventory.fields.${item.label}`) }}</p>
            <p class="mt-1 font-semibold">{{ item.value || '—' }}</p>
          </div>
        </div>
        <div class="mt-4 rounded-2xl border p-4">
          <p class="text-xs text-brand-text-muted">{{ t('inventory.fields.description') }}</p>
          <p class="mt-2 whitespace-pre-wrap">{{ warehouse.description || '—' }}</p>
          <p v-if="warehouse.notes" class="mt-3 whitespace-pre-wrap text-sm text-brand-text-secondary">
            {{ warehouse.notes }}
          </p>
        </div>
      </section>

      <section class="rounded-2xl border p-5">
        <h3 class="mb-3 font-bold">{{ t('inventory.sections.currentStock') }}</h3>
        <div v-if="!balances.length" class="text-sm text-brand-text-muted">{{ t('inventory.balances.empty') }}</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-start text-xs text-brand-text-muted">
                <th class="py-2 pe-4">{{ t('inventory.columns.item') }}</th>
                <th class="py-2 pe-4">{{ t('inventory.columns.unit') }}</th>
                <th class="py-2 pe-4">{{ t('inventory.columns.onHand') }}</th>
                <th class="py-2 pe-4">{{ t('inventory.columns.minimumStock') }}</th>
                <th class="py-2">{{ t('inventory.columns.stockState') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="balance in balances" :key="balance.id" class="border-t border-brand-border">
                <td class="py-2 pe-4">
                  <span class="font-mono text-xs">{{ balance.item?.item_number }}</span>
                  <span class="ms-2 font-semibold">{{ balance.item?.name }}</span>
                </td>
                <td class="py-2 pe-4">{{ balance.item?.unit ? t(`inventory.units.${balance.item.unit}`) : '—' }}</td>
                <td class="py-2 pe-4 font-semibold">{{ formatQuantity(balance.on_hand) }}</td>
                <td class="py-2 pe-4">{{ formatQuantity(balance.item?.minimum_stock) }}</td>
                <td class="py-2">
                  <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="stockStateBadgeClass(balance.stock_state)">
                    {{ t(`inventory.stockState.${balance.stock_state}`) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="rounded-2xl border p-5">
        <h3 class="mb-3 font-bold">{{ t('inventory.sections.recentMovements') }}</h3>
        <div v-if="!movements.length" class="text-sm text-brand-text-muted">{{ t('inventory.movements.empty') }}</div>
        <ul v-else class="space-y-2">
          <li
            v-for="movement in movements"
            :key="movement.id"
            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-brand-border px-3 py-2 text-sm"
          >
            <div>
              <span class="font-mono text-xs">{{ movement.movement_number }}</span>
              <span class="ms-2 rounded-full px-2 py-0.5 text-xs font-semibold" :class="movementTypeBadgeClass(movement.type)">
                {{ t(`inventory.movementType.${movement.type}`) }}
              </span>
              <span class="ms-2">{{ movement.item?.name }}</span>
            </div>
            <div class="font-semibold" :class="movement.direction === 'out' ? 'text-amber-800' : 'text-emerald-800'">
              {{ formatSignedQuantity(movement.quantity, movement.direction) }}
            </div>
          </li>
        </ul>
      </section>

      <EntityDocumentsSection
        linkable-type="warehouse"
        :linkable-id="warehouse.id"
        :link-label="`${warehouse.warehouse_number} — ${warehouse.name}`"
      />
    </template>

    <WarehouseFormDrawer
      :open="drawerOpen"
      :editing="warehouse"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="updateMutation.isPending.value"
      :org-unit-options="orgUnitFormOptions"
      :employee-options="employeeFormOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="Object.assign(form, $event)"
    />
  </div>
</template>
