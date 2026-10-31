<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { ArrowRight, Building2, Lock, Pencil, Plus, Trash2 } from 'lucide-vue-next'

import { listGeoPlaces } from '../api/suppliersApi'
import SupplierFormDrawer from '../components/SupplierFormDrawer.vue'
import SupplierLifecycleActions from '../components/SupplierLifecycleActions.vue'
import SupplierStatusTimeline from '../components/SupplierStatusTimeline.vue'
import {
  useAddSupplierCoverageMutation,
  useCreateSupplierContactMutation,
  useDeleteSupplierContactMutation,
  useDeleteSupplierCoverageMutation,
  useUpdateSupplierMutation,
} from '../mutations/useSupplierMutations'
import { useSupplierQuery } from '../queries/useSupplierQuery'
import type { SupplierFormState } from '../types/suppliers'
import {
  formatSupplierWarnings,
  mapSupplierErrorCode,
  supplierStatusBadgeClass,
  validateSupplierForm,
} from '../validation/supplierValidation'
import { ApiError } from '@/shared/api/http'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

type TabKey = 'basic' | 'categories' | 'contacts' | 'history' | 'banking' | 'documents' | 'evaluations'

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const { can } = usePermissions()

const supplierId = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) ? value : null
})

const { data, isLoading, isError, refetch } = useSupplierQuery(supplierId)
const supplier = computed(() => data.value ?? null)

const activeTab = ref<TabKey>('basic')

const tabs = computed(() => [
  { key: 'basic' as const, label: t('suppliers.tabs.basic') },
  { key: 'categories' as const, label: t('suppliers.tabs.categoriesCoverage') },
  { key: 'contacts' as const, label: t('suppliers.tabs.contacts') },
  { key: 'history' as const, label: t('suppliers.tabs.statusHistory') },
  { key: 'banking' as const, label: t('suppliers.tabs.banking'), phaseII: true },
  { key: 'documents' as const, label: t('suppliers.tabs.documents'), phaseII: true },
  { key: 'evaluations' as const, label: t('suppliers.tabs.evaluations'), phaseII: true },
])

const canEdit = computed(
  () => supplier.value?.status === 'draft' && can('suppliers.update'),
)

const updateMutation = useUpdateSupplierMutation()
const createContactMutation = useCreateSupplierContactMutation()
const deleteContactMutation = useDeleteSupplierContactMutation()
const addCoverageMutation = useAddSupplierCoverageMutation()
const deleteCoverageMutation = useDeleteSupplierCoverageMutation()

const drawerOpen = ref(false)
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

const contactForm = reactive({
  name: '',
  role_title: '',
  email: '',
  phone: '',
  is_primary_contact: false,
})

const coverageForm = reactive({
  supplier_category_id: '' as number | '',
  geo_place_id: '' as number | '',
})

const geoPlaces = ref<{ id: number; name_ar: string }[]>([])

async function loadGeoPlaces(): Promise<void> {
  if (!supplier.value) return
  const result = await listGeoPlaces({
    country_code: supplier.value.country_code,
    per_page: 100,
  })
  geoPlaces.value = result.data.map((p) => ({ id: p.id, name_ar: p.name_ar }))
}

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

function openEdit(): void {
  if (!supplier.value) return
  Object.assign(form, {
    display_name: supplier.value.display_name,
    legal_name: supplier.value.legal_name ?? '',
    supplier_type: supplier.value.supplier_type,
    country_code: supplier.value.country_code,
    legal_id_type: supplier.value.legal_id_type ?? '',
    legal_id_value: supplier.value.legal_id_value ?? '',
    notes: supplier.value.notes ?? '',
    category_ids: supplier.value.categories?.map((c) => c.id) ?? [],
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

async function submitForm(): Promise<void> {
  if (!supplier.value) return
  const validation = validateSupplierForm(form)
  if (Object.keys(validation).length > 0) {
    Object.assign(fieldErrors, validation)
    return
  }
  try {
    const result = await updateMutation.mutateAsync({
      id: supplier.value.id,
      payload: {
        display_name: form.display_name.trim(),
        legal_name: form.legal_name.trim() || null,
        supplier_type: form.supplier_type,
        country_code: form.country_code.trim(),
        legal_id_type: form.legal_id_type.trim() || null,
        legal_id_value: form.legal_id_value.trim() || null,
        notes: form.notes.trim() || null,
        category_ids: form.category_ids,
      },
    })
    if (result.warnings.length) {
      toast.info(formatSupplierWarnings(result.warnings))
    }
    toast.success(t('suppliers.toasts.updated'))
    drawerOpen.value = false
    await refetch()
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function submitContact(): Promise<void> {
  if (!supplier.value || !contactForm.name.trim()) {
    toast.error(t('suppliers.validation.required'))
    return
  }
  try {
    await createContactMutation.mutateAsync({
      supplierId: supplier.value.id,
      payload: {
        name: contactForm.name.trim(),
        role_title: contactForm.role_title.trim() || null,
        email: contactForm.email.trim() || null,
        phone: contactForm.phone.trim() || null,
        is_primary_contact: contactForm.is_primary_contact,
      },
    })
    toast.success(t('suppliers.toasts.contactCreated'))
    contactForm.name = ''
    contactForm.role_title = ''
    contactForm.email = ''
    contactForm.phone = ''
    contactForm.is_primary_contact = false
    await refetch()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function removeContact(contactId: number): Promise<void> {
  if (!supplier.value) return
  try {
    await deleteContactMutation.mutateAsync({ supplierId: supplier.value.id, contactId })
    toast.success(t('suppliers.toasts.contactDeleted'))
    await refetch()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function submitCoverage(): Promise<void> {
  if (!supplier.value || coverageForm.supplier_category_id === '' || coverageForm.geo_place_id === '') {
    toast.error(t('suppliers.coverageFormIncomplete'))
    return
  }
  try {
    await addCoverageMutation.mutateAsync({
      supplierId: supplier.value.id,
      payload: {
        supplier_category_id: Number(coverageForm.supplier_category_id),
        geo_place_id: Number(coverageForm.geo_place_id),
      },
    })
    toast.success(t('suppliers.toasts.coverageAdded'))
    coverageForm.geo_place_id = ''
    await refetch()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function removeCoverage(coverageId: number): Promise<void> {
  if (!supplier.value) return
  try {
    await deleteCoverageMutation.mutateAsync({ supplierId: supplier.value.id, coverageId })
    toast.success(t('suppliers.toasts.coverageDeleted'))
    await refetch()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

const categoryCoverageOptions = computed<AppSelectOption[]>(() =>
  (supplier.value?.categories ?? []).map((c) => ({ value: c.id, label: c.name })),
)

const geoPlaceOptions = computed<AppSelectOption[]>(() =>
  geoPlaces.value.map((p) => ({ value: p.id, label: p.name_ar })),
)

function onTabSelect(tab: TabKey, phaseII?: boolean): void {
  if (phaseII) return
  activeTab.value = tab
  if (tab === 'categories') {
    void loadGeoPlaces()
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <RouterLink
        to="/app/suppliers"
        class="inline-flex items-center gap-1 text-sm font-semibold text-brand-primary"
      >
        <ArrowRight class="h-4 w-4" />
        {{ t('suppliers.backToList') }}
      </RouterLink>
    </div>

    <div v-if="isLoading" class="text-sm text-brand-text-muted">{{ t('suppliers.loadingDetails') }}</div>
    <div v-else-if="isError || !supplier" class="text-sm text-red-600">{{ t('suppliers.errors.generic') }}</div>

    <template v-else>
      <header class="rounded-2xl border border-brand-border bg-brand-surface p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="flex items-start gap-3">
            <div class="rounded-xl bg-brand-primary/10 p-3 text-brand-primary">
              <Building2 class="h-6 w-6" />
            </div>
            <div>
              <p class="font-mono text-xs text-brand-text-muted">{{ supplier.supplier_number }}</p>
              <h1 class="text-xl font-bold text-brand-text">{{ supplier.display_name }}</h1>
              <div class="mt-2 flex flex-wrap items-center gap-2">
                <span
                  class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="supplierStatusBadgeClass(supplier.status)"
                >
                  {{ t(`suppliers.status.${supplier.status}`) }}
                </span>
                <span class="text-sm text-brand-text-secondary">
                  {{ t(`suppliers.type.${supplier.supplier_type}`) }}
                </span>
              </div>
            </div>
          </div>
          <PermissionGuard permission="suppliers.update">
            <button
              v-if="canEdit"
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-brand-border px-3 py-2 text-sm font-semibold"
              @click="openEdit"
            >
              <Pencil class="h-4 w-4" />
              {{ t('suppliers.editCta') }}
            </button>
          </PermissionGuard>
        </div>
      </header>

      <SupplierLifecycleActions :supplier="supplier" @refreshed="refetch()" />

      <nav class="flex flex-wrap gap-2 border-b border-brand-border pb-1">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="rounded-t-lg px-3 py-2 text-sm font-semibold transition"
          :class="
            tab.phaseII
              ? 'cursor-not-allowed text-brand-text-muted opacity-70'
              : activeTab === tab.key
                ? 'border-b-2 border-brand-primary text-brand-primary'
                : 'text-brand-text-secondary hover:text-brand-text'
          "
          :disabled="tab.phaseII"
          @click="onTabSelect(tab.key, tab.phaseII)"
        >
          {{ tab.label }}
          <span v-if="tab.phaseII" class="ms-1 text-[10px] font-normal">{{
            t('suppliers.phaseIIPlaceholder')
          }}</span>
        </button>
      </nav>

      <div v-if="activeTab === 'basic'" class="rounded-2xl border border-brand-border bg-brand-surface p-5">
        <dl class="grid gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs text-brand-text-muted">{{ t('suppliers.fields.legalName') }}</dt>
            <dd class="font-semibold">{{ supplier.legal_name || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-brand-text-muted">{{ t('suppliers.fields.countryCode') }}</dt>
            <dd class="font-semibold">{{ supplier.country_code }}</dd>
          </div>
          <div>
            <dt class="text-xs text-brand-text-muted">{{ t('suppliers.fields.legalIdType') }}</dt>
            <dd class="font-semibold">{{ supplier.legal_id_type || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-brand-text-muted">{{ t('suppliers.fields.legalIdValue') }}</dt>
            <dd class="font-semibold">{{ supplier.legal_id_value || '—' }}</dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-xs text-brand-text-muted">{{ t('suppliers.fields.notes') }}</dt>
            <dd class="whitespace-pre-wrap">{{ supplier.notes || '—' }}</dd>
          </div>
        </dl>
      </div>

      <div v-else-if="activeTab === 'categories'" class="space-y-4">
        <section class="rounded-2xl border border-brand-border bg-brand-surface p-5">
          <h3 class="font-bold text-brand-text">{{ t('suppliers.linkedCategories') }}</h3>
          <p class="mt-2 text-sm text-brand-text-secondary">
            {{ supplier.categories?.map((c) => c.name).join('، ') || t('suppliers.noCategories') }}
          </p>
        </section>

        <PermissionGuard permission="suppliers.update">
          <section class="rounded-2xl border border-brand-border bg-brand-surface p-5">
            <h3 class="font-bold text-brand-text">{{ t('suppliers.addCoverage') }}</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
              <AppSelect
                v-model="coverageForm.supplier_category_id"
                :options="categoryCoverageOptions"
                :label="t('suppliers.fields.categories')"
              />
              <AppSelect
                v-model="coverageForm.geo_place_id"
                :options="geoPlaceOptions"
                :label="t('suppliers.fields.geoPlace')"
              />
            </div>
            <button
              type="button"
              class="mt-4 inline-flex items-center gap-2 rounded-xl bg-brand-primary px-3 py-2 text-sm font-semibold text-white"
              @click="submitCoverage"
            >
              <Plus class="h-4 w-4" />
              {{ t('suppliers.saveCoverage') }}
            </button>
          </section>
        </PermissionGuard>

        <section class="rounded-2xl border border-brand-border bg-brand-surface p-5">
          <h3 class="font-bold text-brand-text">{{ t('suppliers.coverageList') }}</h3>
          <ul v-if="(supplier.coverages?.length ?? 0) > 0" class="mt-3 space-y-2">
            <li
              v-for="coverage in supplier.coverages"
              :key="coverage.id"
              class="flex items-center justify-between rounded-xl border border-brand-border px-3 py-2 text-sm"
            >
              <span>
                {{ coverage.category?.name ?? coverage.supplier_category_id }}
                ·
                {{ coverage.geo_place?.name_ar ?? coverage.geo_place_id }}
              </span>
              <PermissionGuard permission="suppliers.update">
                <button type="button" class="text-red-600" @click="removeCoverage(coverage.id)">
                  <Trash2 class="h-4 w-4" />
                </button>
              </PermissionGuard>
            </li>
          </ul>
          <p v-else class="mt-3 text-sm text-brand-text-muted">{{ t('suppliers.coverageEmpty') }}</p>
        </section>
      </div>

      <div v-else-if="activeTab === 'contacts'" class="space-y-4">
        <PermissionGuard permission="suppliers.update">
          <section class="rounded-2xl border border-brand-border bg-brand-surface p-5">
            <h3 class="font-bold text-brand-text">{{ t('suppliers.addContact') }}</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
              <input v-model="contactForm.name" type="text" class="rounded-xl border px-3 py-2 text-sm" :placeholder="t('suppliers.fields.contactName')" />
              <input v-model="contactForm.role_title" type="text" class="rounded-xl border px-3 py-2 text-sm" :placeholder="t('suppliers.fields.contactRole')" />
              <input v-model="contactForm.email" type="email" class="rounded-xl border px-3 py-2 text-sm" :placeholder="t('suppliers.fields.contactEmail')" />
              <input v-model="contactForm.phone" type="text" class="rounded-xl border px-3 py-2 text-sm" :placeholder="t('suppliers.fields.contactPhone')" />
            </div>
            <label class="mt-3 flex items-center gap-2 text-sm">
              <input v-model="contactForm.is_primary_contact" type="checkbox" />
              {{ t('suppliers.fields.primaryContact') }}
            </label>
            <button
              type="button"
              class="mt-4 inline-flex items-center gap-2 rounded-xl bg-brand-primary px-3 py-2 text-sm font-semibold text-white"
              @click="submitContact"
            >
              <Plus class="h-4 w-4" />
              {{ t('suppliers.saveContact') }}
            </button>
          </section>
        </PermissionGuard>

        <section class="rounded-2xl border border-brand-border bg-brand-surface p-5">
          <ul v-if="(supplier.contacts?.length ?? 0) > 0" class="space-y-2">
            <li
              v-for="contact in supplier.contacts"
              :key="contact.id"
              class="flex items-center justify-between rounded-xl border border-brand-border px-3 py-2 text-sm"
            >
              <div>
                <p class="font-semibold">{{ contact.name }}</p>
                <p class="text-brand-text-secondary">
                  {{ contact.role_title || '—' }} · {{ contact.email || '—' }} · {{ contact.phone || '—' }}
                </p>
              </div>
              <PermissionGuard permission="suppliers.update">
                <button type="button" class="text-red-600" @click="removeContact(contact.id)">
                  <Trash2 class="h-4 w-4" />
                </button>
              </PermissionGuard>
            </li>
          </ul>
          <p v-else class="text-sm text-brand-text-muted">{{ t('suppliers.contactsEmpty') }}</p>
        </section>
      </div>

      <SupplierStatusTimeline
        v-else-if="activeTab === 'history'"
        :transitions="supplier.transitions ?? []"
      />

      <section
        v-if="['banking', 'documents', 'evaluations'].includes(activeTab)"
        class="rounded-2xl border border-dashed border-brand-border bg-brand-bg/40 p-8 text-center"
      >
        <Lock class="mx-auto h-8 w-8 text-brand-text-muted" />
        <p class="mt-3 text-sm font-semibold text-brand-text">{{ t('suppliers.phaseIILockedTitle') }}</p>
        <p class="mt-1 text-sm text-brand-text-secondary">{{ t('suppliers.phaseIILockedBody') }}</p>
      </section>
    </template>

    <SupplierFormDrawer
      v-if="supplier"
      :open="drawerOpen"
      :editing="supplier"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="updateMutation.isPending.value"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="Object.assign(form, $event)"
    />
  </div>
</template>
