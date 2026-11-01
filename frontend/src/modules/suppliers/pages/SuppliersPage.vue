<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import { Eye, FolderTree, Pencil, Plus, Search, ShieldCheck } from 'lucide-vue-next'

import { listSupplierCategories } from '../api/categoriesApi'
import CategoriesManagerDrawer from '../components/CategoriesManagerDrawer.vue'
import EligibilityConfigDrawer from '../components/EligibilityConfigDrawer.vue'
import SupplierFormDrawer from '../components/SupplierFormDrawer.vue'
import {
  useCreateSupplierMutation,
  useUpdateSupplierMutation,
} from '../mutations/useSupplierMutations'
import { useSuppliersQuery } from '../queries/useSuppliersQuery'
import type {
  Supplier,
  SupplierFormState,
  SupplierStatus,
  SupplierType,
  SupplierWarning,
} from '../types/suppliers'
import {
  SUPPLIER_STATUSES,
  SUPPLIER_TYPES,
  formatSupplierWarnings,
  mapSupplierErrorCode,
  resolveSuppliersListState,
  supplierStatusBadgeClass,
  supplierStatusDotClass,
  validateSupplierForm,
} from '../validation/supplierValidation'
import { ApiError } from '@/shared/api/http'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { namedCodeOption, toSelectId } from '@/shared/lookups/selectOptions'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useToast } from '@/shared/composables/useToast'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

const { t } = useI18n()
const router = useRouter()
const toast = useToast()

const filters = reactive({
  search: '',
  status: 'all' as SupplierStatus | 'all',
  supplier_type: 'all' as SupplierType | 'all',
  category_id: '' as number | '',
  page: 1,
  per_page: 15,
})

const committedSearch = useDebouncedRef(() => filters.search)

const queryParams = computed(() => ({
  q: committedSearch.value || undefined,
  status: filters.status === 'all' ? undefined : filters.status,
  category_id: filters.category_id === '' ? undefined : filters.category_id,
  supplier_type: filters.supplier_type === 'all' ? undefined : filters.supplier_type,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch, isFetching } = useSuppliersQuery(queryParams)

const suppliers = computed(() => data.value?.data ?? [])

const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveSuppliersListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: suppliers.value.length,
  }),
)

const fetchCategories = (params: { search?: string; page: number; per_page: number }) =>
  listSupplierCategories({ ...params, is_active: true })

const createMutation = useCreateSupplierMutation()
const updateMutation = useUpdateSupplierMutation()

const drawerOpen = ref(false)
const categoriesOpen = ref(false)
const eligibilityOpen = ref(false)
const editing = ref<Supplier | null>(null)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<SupplierFormState>({
  display_name: '',
  legal_name: '',
  supplier_type: 'company',
  country_code: 'SA',
  legal_id_type: '',
  legal_id_value: '',
  notes: '',
  category_ids: [],
})

const isFormSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: 'all', label: t('suppliers.filters.allStatuses') },
  ...SUPPLIER_STATUSES.map((status) => ({
    value: status,
    label: t(`suppliers.status.${status}`),
  })),
])

const emptyCategory = computed<AppSelectOption>(() => ({
  value: '',
  label: t('suppliers.filters.allCategories'),
}))

const typeOptions = computed<AppSelectOption[]>(() => [
  { value: 'all', label: t('suppliers.filters.allTypes') },
  ...SUPPLIER_TYPES.map((type) => ({
    value: type,
    label: t(`suppliers.type.${type}`),
  })),
])

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.status !== 'all') count += 1
  if (filters.supplier_type !== 'all') count += 1
  if (filters.category_id !== '') count += 1
  return count
})

function resetFilters(): void {
  filters.status = 'all'
  filters.supplier_type = 'all'
  filters.category_id = ''
}

watch(
  () => [committedSearch.value, filters.status, filters.category_id, filters.supplier_type],
  () => {
    filters.page = 1
  },
)

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return t('suppliers.errors.generic')
  }
  const mapped = mapSupplierErrorCode(error.code)
  if (mapped !== 'generic') {
    return t(`suppliers.errors.${mapped}`)
  }
  return error.message || t('suppliers.errors.generic')
}

function showWarnings(warnings: SupplierWarning[]): void {
  if (warnings.length === 0) return
  toast.info(formatSupplierWarnings(warnings))
}

function openCreate(): void {
  editing.value = null
  Object.assign(form, {
    display_name: '',
    legal_name: '',
    supplier_type: 'company',
    country_code: 'SA',
    legal_id_type: '',
    legal_id_value: '',
    notes: '',
    category_ids: [],
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function openEdit(supplier: Supplier): void {
  if (supplier.status !== 'draft') return
  editing.value = supplier
  Object.assign(form, {
    display_name: supplier.display_name,
    legal_name: supplier.legal_name ?? '',
    supplier_type: supplier.supplier_type,
    country_code: supplier.country_code,
    legal_id_type: supplier.legal_id_type ?? '',
    legal_id_value: supplier.legal_id_value ?? '',
    notes: supplier.notes ?? '',
    category_ids: supplier.categories?.map((c) => c.id) ?? [],
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

async function submitForm(): Promise<void> {
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const validation = validateSupplierForm(form)
  if (validation.display_name || validation.supplier_type || validation.country_code) {
    if (validation.display_name) fieldErrors.display_name = validation.display_name
    if (validation.supplier_type) fieldErrors.supplier_type = validation.supplier_type
    if (validation.country_code) fieldErrors.country_code = validation.country_code
    return
  }

  const payload = {
    display_name: form.display_name.trim(),
    legal_name: form.legal_name.trim() || null,
    supplier_type: form.supplier_type,
    country_code: form.country_code.trim(),
    legal_id_type: form.legal_id_type.trim() || null,
    legal_id_value: form.legal_id_value.trim() || null,
    notes: form.notes.trim() || null,
    category_ids: form.category_ids,
  }

  try {
    if (editing.value) {
      const result = await updateMutation.mutateAsync({ id: editing.value.id, payload })
      showWarnings(result.warnings)
      toast.success(t('suppliers.toasts.updated'))
    } else {
      const result = await createMutation.mutateAsync(payload)
      showWarnings(result.warnings)
      toast.success(t('suppliers.toasts.created'))
      drawerOpen.value = false
      await router.push(`/app/suppliers/${result.supplier.id}`)
      return
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader :title="t('suppliers.title')" :subtitle="t('suppliers.subtitle')">
      <template #actions>
        <PermissionGuard :any="['suppliers.view', 'suppliers.manage_eligibility_config']">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 py-2 text-sm font-semibold"
            @click="eligibilityOpen = true"
          >
            <ShieldCheck class="h-4 w-4" />
            {{ t('suppliers.eligibility.configLink') }}
          </button>
        </PermissionGuard>
        <PermissionGuard permission="suppliers.update">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 py-2 text-sm font-semibold"
            @click="categoriesOpen = true"
          >
            <FolderTree class="h-4 w-4" />
            {{ t('suppliers.categoriesLink') }}
          </button>
        </PermissionGuard>
        <PermissionGuard permission="suppliers.create">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-3 py-2 text-sm font-semibold text-white"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            {{ t('suppliers.add') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
        <div class="relative flex-1">
          <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted" />
          <input
            v-model="filters.search"
            type="search"
            class="w-full rounded-xl border border-brand-border py-2 ps-9 pe-3 text-sm"
            :placeholder="t('suppliers.searchPlaceholder')"
          />
        </div>
        <AppMobileFilters :active-count="activeFilterCount" @reset="resetFilters">
          <AppSelect v-model="filters.status" :options="statusOptions" :label="t('suppliers.fields.status')" />
          <AppSelect v-model="filters.supplier_type" :options="typeOptions" :label="t('suppliers.fields.supplierType')" />
          <AppRemoteSelect
            :model-value="filters.category_id"
            query-key="supplier-categories"
            :fetcher="fetchCategories"
            :map-option="namedCodeOption"
            :empty-option="emptyCategory"
            @update:model-value="filters.category_id = toSelectId($event)"
          />
        </AppMobileFilters>
      </div>
    </div>

    <div class="rounded-2xl border border-brand-border bg-brand-surface">
      <div v-if="listState === 'loading'" class="p-10 text-center text-sm text-brand-text-muted">
        {{ t('suppliers.loading') }}
      </div>
      <div v-else-if="listState === 'error'" class="p-10 text-center">
        <p class="text-sm text-red-600">{{ t('suppliers.errors.generic') }}</p>
        <button
          type="button"
          class="mt-3 text-sm font-semibold text-brand-primary"
          @click="() => { void refetch() }"
        >
          {{ t('suppliers.retry') }}
        </button>
      </div>
      <div v-else-if="listState === 'empty'" class="p-10 text-center text-sm text-brand-text-muted">
        {{ t('suppliers.empty') }}
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="border-b border-brand-border bg-brand-bg/50 text-start text-brand-text-secondary">
            <tr>
              <th class="px-4 py-3 font-semibold">{{ t('suppliers.columns.number') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('suppliers.columns.displayName') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('suppliers.columns.type') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('suppliers.columns.status') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('suppliers.columns.categories') }}</th>
              <th class="px-4 py-3 font-semibold">{{ t('suppliers.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="supplier in suppliers"
              :key="supplier.id"
              class="border-b border-brand-border/60 last:border-0"
            >
              <td class="px-4 py-3 font-mono text-xs">{{ supplier.supplier_number }}</td>
              <td class="px-4 py-3 font-semibold text-brand-text">{{ supplier.display_name }}</td>
              <td class="px-4 py-3">{{ t(`suppliers.type.${supplier.supplier_type}`) }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="supplierStatusBadgeClass(supplier.status)"
                >
                  <span class="h-1.5 w-1.5 rounded-full" :class="supplierStatusDotClass(supplier.status)" />
                  {{ t(`suppliers.status.${supplier.status}`) }}
                </span>
              </td>
              <td class="px-4 py-3 text-brand-text-secondary">
                {{ supplier.categories?.map((c) => c.name).join('، ') || '—' }}
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-1">
                  <RouterLink
                    :to="`/app/suppliers/${supplier.id}`"
                    class="rounded-lg p-2 hover:bg-brand-bg"
                    :aria-label="t('suppliers.viewDetails')"
                  >
                    <Eye class="h-4 w-4" />
                  </RouterLink>
                  <PermissionGuard permission="suppliers.update">
                    <button
                      v-if="supplier.status === 'draft'"
                      type="button"
                      class="rounded-lg p-2 hover:bg-brand-bg"
                      @click="openEdit(supplier)"
                    >
                      <Pencil class="h-4 w-4" />
                    </button>
                  </PermissionGuard>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between border-t border-brand-border px-4 py-3 text-sm"
      >
        <span class="text-brand-text-muted">
          {{ t('suppliers.pagination', { page: meta.current_page, total: meta.last_page }) }}
        </span>
        <div class="flex gap-2">
          <button
            type="button"
            class="rounded-lg border border-brand-border px-3 py-1 disabled:opacity-50"
            :disabled="filters.page <= 1 || isFetching"
            @click="filters.page -= 1"
          >
            {{ t('suppliers.prev') }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-brand-border px-3 py-1 disabled:opacity-50"
            :disabled="filters.page >= meta.last_page || isFetching"
            @click="filters.page += 1"
          >
            {{ t('suppliers.next') }}
          </button>
        </div>
      </footer>
    </div>

    <SupplierFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="Object.assign(form, $event)"
    />

    <CategoriesManagerDrawer :open="categoriesOpen" @close="categoriesOpen = false" />
    <EligibilityConfigDrawer :open="eligibilityOpen" @close="eligibilityOpen = false" />
  </div>
</template>
