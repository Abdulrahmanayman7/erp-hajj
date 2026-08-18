<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  ArrowRight,
  Building2,
  FileText,
  MapPin,
  Package,
  Pencil,
  UserRound,
} from 'lucide-vue-next'

import EntityDocumentsSection from '@/modules/documents/components/EntityDocumentsSection.vue'
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
  stockStateDotClass,
  validateWarehouseForm,
} from '../validation/inventoryValidation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()

const id = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) ? value : null
})

const { data, isLoading, isError, refetch } = useWarehouseQuery(id)
const warehouse = computed(() => data.value ?? null)

const balancesParams = computed(() => ({
  warehouse_id: id.value ?? undefined,
  per_page: 100,
}))
const movementsParams = computed(() => ({
  warehouse_id: id.value ?? undefined,
  per_page: 10,
}))

const { data: balancesData } = useInventoryBalancesQuery(balancesParams)
const { data: movementsData } = useInventoryMovementsQuery(movementsParams)

const balances = computed(() => balancesData.value?.data ?? [])
const movements = computed(() => movementsData.value?.data ?? [])
const kpis = computed(() => computeWarehouseStockKpis(balances.value))

const { data: orgUnitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })

const orgUnitFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('inventory.noOrgUnit') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({ value: u.id, label: u.name })),
])

const updateMutation = useUpdateWarehouseMutation()
const isFormSubmitting = computed(() => updateMutation.isPending.value)
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
  if (!warehouse.value || !can('warehouses.update')) return
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
        organization_unit_id:
          form.organization_unit_id === '' ? null : Number(form.organization_unit_id),
        responsible_employee_id:
          form.responsible_employee_id === '' ? null : Number(form.responsible_employee_id),
        is_active: form.is_active,
        notes: form.notes.trim() || null,
      },
    })
    toast.success(t('inventory.toasts.warehouseUpdated'))
    drawerOpen.value = false
    await refetch()
  } catch (error) {
    if (error instanceof ApiError) {
      const mapped = mapInventoryErrorCode(error.code)
      formError.value =
        mapped !== 'generic'
          ? t(`inventory.errors.${mapped}`)
          : error.message || t('inventory.errors.generic')
    } else {
      formError.value = t('inventory.errors.generic')
    }
  }
}

function assignForm(next: WarehouseFormState): void {
  Object.assign(form, next)
}
</script>

<template>
  <div class="mx-auto max-w-[1200px] space-y-5">
    <div class="flex flex-wrap items-center gap-3">
      <button
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg"
        @click="router.push('/app/warehouses')"
      >
        <ArrowRight class="h-4 w-4" />
        {{ t('inventory.warehouses.backToList') }}
      </button>
    </div>

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('inventory.loadingDetails') }}
    </div>

    <div
      v-else-if="isError || !warehouse"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('inventory.errors.loadDetails') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('inventory.retry') }}
      </button>
    </div>

    <template v-else>
      <section class="rounded-2xl border border-brand-border bg-brand-surface px-5 py-5 sm:px-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p
                class="font-mono text-xs font-semibold tracking-wide text-brand-text-muted"
                dir="ltr"
              >
                {{ warehouse.warehouse_number }}
              </p>
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold tracking-wide text-brand-text ring-1 ring-inset"
                :class="
                  warehouse.is_active
                    ? 'bg-emerald-100 ring-emerald-300/80'
                    : 'bg-neutral-100 ring-neutral-300/80'
                "
              >
                <span
                  class="h-1.5 w-1.5 shrink-0 rounded-full"
                  :class="warehouse.is_active ? 'bg-emerald-500' : 'bg-neutral-400'"
                  aria-hidden="true"
                />
                {{ warehouse.is_active ? t('inventory.status.active') : t('inventory.status.inactive') }}
              </span>
            </div>
            <h2 class="mt-2 text-[1.65rem] font-bold leading-snug text-brand-text sm:text-[1.85rem]">
              {{ warehouse.name }}
            </h2>
            <p class="mt-2 text-sm text-brand-text-secondary">
              <span class="font-semibold text-brand-text">
                {{ warehouse.responsible_employee?.full_name ?? t('inventory.noEmployee') }}
              </span>
              <span class="mx-1.5 text-brand-text-muted">·</span>
              {{ warehouse.organization_unit?.name ?? t('inventory.noOrgUnit') }}
              <template v-if="warehouse.location">
                <span class="mx-1.5 text-brand-text-muted">·</span>
                {{ warehouse.location }}
              </template>
            </p>
          </div>

          <PermissionGuard v-if="can('warehouses.update')" permission="warehouses.update">
            <button
              type="button"
              class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-primary-soft"
              @click="openEdit"
            >
              <Pencil class="h-4 w-4" />
              {{ t('inventory.actions.edit') }}
            </button>
          </PermissionGuard>
        </div>
      </section>

      <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-5">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('inventory.warehouses.detailsSummary') }}
              </h3>
            </header>
            <dl class="grid gap-0 sm:grid-cols-2">
              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <MapPin class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('inventory.fields.location') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ warehouse.location || '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Building2 class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('inventory.fields.organizationUnit') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ warehouse.organization_unit?.name || '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e sm:border-b-0">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <UserRound class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('inventory.fields.responsibleEmployee') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ warehouse.responsible_employee?.full_name || '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <UserRound class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('inventory.fields.createdBy') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ warehouse.created_by?.name || '—' }}
                  </dd>
                </div>
              </div>
            </dl>
          </section>

          <section
            v-if="warehouse.description"
            class="rounded-2xl border border-brand-border bg-brand-surface"
          >
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('inventory.fields.description') }}
              </h3>
            </header>
            <p class="whitespace-pre-wrap px-5 py-4 text-sm leading-relaxed text-brand-text">
              {{ warehouse.description }}
            </p>
          </section>

          <section
            v-if="warehouse.notes"
            class="rounded-2xl border border-brand-border bg-brand-surface"
          >
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('inventory.fields.notes') }}</h3>
            </header>
            <p class="whitespace-pre-wrap px-5 py-4 text-sm leading-relaxed text-brand-text-secondary">
              {{ warehouse.notes }}
            </p>
          </section>

          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header
              class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-5 py-4"
            >
              <div class="flex items-center gap-2">
                <Package class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
                <h3 class="text-sm font-bold text-brand-text">
                  {{ t('inventory.sections.currentStock') }}
                </h3>
              </div>
              <RouterLink
                to="/app/inventory"
                class="text-xs font-semibold text-brand-primary-dark hover:underline"
              >
                {{ t('inventory.warehouses.viewInventory') }}
              </RouterLink>
            </header>

            <p
              v-if="!balances.length"
              class="px-5 py-8 text-center text-sm text-brand-text-muted"
            >
              {{ t('inventory.balances.empty') }}
            </p>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full border-separate border-spacing-0 text-sm">
                <thead>
                  <tr class="bg-[#F4F6F5]">
                    <th
                      v-for="key in ['item', 'unit', 'onHand', 'minimumStock', 'stockState']"
                      :key="key"
                      class="whitespace-nowrap border-b border-brand-border px-5 py-3 text-center text-xs font-bold text-brand-text"
                    >
                      {{ t(`inventory.columns.${key}`) }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(balance, index) in balances"
                    :key="balance.id"
                    class="group"
                    :class="index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'"
                  >
                    <td
                      class="max-w-[16rem] whitespace-nowrap border-b border-brand-border/80 px-5 py-3 text-center transition-colors group-hover:bg-[#EDF6F1]"
                    >
                      <RouterLink
                        v-if="balance.item"
                        :to="`/app/inventory/items/${balance.item.id}`"
                        class="inline-flex max-w-full items-center justify-center gap-2"
                        :title="balance.item.name"
                      >
                        <span
                          class="inline-flex shrink-0 items-center rounded-lg border border-brand-border bg-brand-bg px-2 py-1 font-mono text-[11px] font-bold text-brand-text"
                          dir="ltr"
                        >
                          {{ balance.item.item_number }}
                        </span>
                        <span
                          class="truncate font-semibold text-brand-text transition hover:text-brand-primary-dark hover:underline"
                        >
                          {{ balance.item.name }}
                        </span>
                      </RouterLink>
                      <span v-else class="text-brand-text">—</span>
                    </td>
                    <td
                      class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3 text-center text-brand-text transition-colors group-hover:bg-[#EDF6F1]"
                    >
                      {{ balance.item?.unit ? t(`inventory.units.${balance.item.unit}`) : '—' }}
                    </td>
                    <td
                      class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3 text-center font-semibold text-brand-text transition-colors group-hover:bg-[#EDF6F1]"
                      dir="ltr"
                    >
                      {{ formatQuantity(balance.on_hand) }}
                    </td>
                    <td
                      class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3 text-center text-brand-text transition-colors group-hover:bg-[#EDF6F1]"
                      dir="ltr"
                    >
                      {{ formatQuantity(balance.item?.minimum_stock) }}
                    </td>
                    <td
                      class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3 text-center transition-colors group-hover:bg-[#EDF6F1]"
                    >
                      <span
                        class="inline-flex min-w-[6.25rem] items-center justify-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold shadow-sm"
                        :class="stockStateBadgeClass(balance.stock_state)"
                      >
                        <span
                          class="h-1.5 w-1.5 shrink-0 rounded-full"
                          :class="stockStateDotClass(balance.stock_state)"
                          aria-hidden="true"
                        />
                        {{ t(`inventory.stockState.${balance.stock_state}`) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header
              class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-5 py-4"
            >
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('inventory.sections.recentMovements') }}
              </h3>
              <RouterLink
                to="/app/inventory/movements"
                class="text-xs font-semibold text-brand-primary-dark hover:underline"
              >
                {{ t('inventory.warehouses.viewMovements') }}
              </RouterLink>
            </header>

            <p
              v-if="!movements.length"
              class="px-5 py-8 text-center text-sm text-brand-text-muted"
            >
              {{ t('inventory.movements.empty') }}
            </p>
            <ul v-else class="divide-y divide-brand-border/80">
              <li
                v-for="movement in movements"
                :key="movement.id"
                class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-[#EDF6F1]/60"
              >
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                  <span
                    class="inline-flex items-center rounded-lg border border-brand-border bg-brand-bg px-2 py-1 font-mono text-[11px] font-bold text-brand-text"
                    dir="ltr"
                  >
                    {{ movement.movement_number }}
                  </span>
                  <span
                    class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                    :class="movementTypeBadgeClass(movement.type)"
                  >
                    {{ t(`inventory.movementType.${movement.type}`) }}
                  </span>
                  <span class="truncate text-sm font-semibold text-brand-text">
                    {{ movement.item?.name ?? '—' }}
                  </span>
                </div>
                <span
                  class="shrink-0 text-sm font-bold tabular-nums text-brand-text"
                  dir="ltr"
                >
                  {{ formatSignedQuantity(movement.quantity, movement.direction) }}
                </span>
              </li>
            </ul>
          </section>

          <EntityDocumentsSection
            linkable-type="warehouse"
            :linkable-id="warehouse.id"
            :link-label="`${warehouse.warehouse_number} — ${warehouse.name}`"
          />
        </div>

        <aside class="space-y-5 xl:sticky xl:top-4 xl:self-start">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('inventory.warehouses.kpiTitle') }}
              </h3>
              <p class="mt-1 text-xs text-brand-text-secondary">
                {{ t('inventory.warehouses.kpiHint') }}
              </p>
            </header>
            <div class="space-y-3 px-4 py-4">
              <div
                class="rounded-xl border border-brand-border bg-brand-bg/50 px-4 py-3"
              >
                <p class="text-xs font-semibold text-brand-text-muted">
                  {{ t('inventory.kpis.distinctItems') }}
                </p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-brand-text">
                  {{ kpis.distinctItems }}
                </p>
              </div>
              <div
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"
              >
                <p class="text-xs font-semibold text-brand-text-muted">
                  {{ t('inventory.kpis.lowStock') }}
                </p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-brand-text">
                  {{ kpis.lowStock }}
                </p>
              </div>
              <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                <p class="text-xs font-semibold text-brand-text-muted">
                  {{ t('inventory.kpis.outOfStock') }}
                </p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-brand-text">
                  {{ kpis.outOfStock }}
                </p>
              </div>
            </div>
          </section>
        </aside>
      </div>
    </template>

    <WarehouseFormDrawer
      :open="drawerOpen"
      :editing="warehouse"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      :org-unit-options="orgUnitFormOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="assignForm"
    />
  </div>
</template>
