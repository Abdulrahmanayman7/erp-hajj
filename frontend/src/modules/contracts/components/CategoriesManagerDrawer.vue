<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Pencil, Plus, Power, PowerOff, Trash2 } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useFormDirty } from '@/shared/composables/useFormDirty'
import { useToast } from '@/shared/composables/useToast'

import {
  useActivateCategoryMutation,
  useCreateCategoryMutation,
  useDeactivateCategoryMutation,
  useDeleteCategoryMutation,
  useUpdateCategoryMutation,
} from '../mutations/useCategoryMutations'
import { useCategoriesQuery } from '../queries/useCategoriesQuery'
import type { CategoryFormState, ContractCategory } from '../types/categories'
import { mapContractErrorCode, validateCategoryForm } from '../validation/contractValidation'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
}>()

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()

const search = ref('')
const queryParams = computed(() => ({
  search: search.value || undefined,
  per_page: 100,
}))

const { data, isLoading, isError, refetch } = useCategoriesQuery(queryParams, {
  enabled: computed(() => props.open),
})

const createMutation = useCreateCategoryMutation()
const updateMutation = useUpdateCategoryMutation()
const activateMutation = useActivateCategoryMutation()
const deactivateMutation = useDeactivateCategoryMutation()
const deleteMutation = useDeleteCategoryMutation()

const categories = computed(() => data.value?.data ?? [])
const editing = ref<ContractCategory | null>(null)
const formOpen = ref(false)
const formError = ref('')
const form = reactive<CategoryFormState>({ name: '', code: '' })
const fieldErrors = reactive<Record<string, string>>({})

const { dirty: formDirty, capture: captureFormDirty, reset: resetFormDirty } = useFormDirty(form, {
  enabled: formOpen,
})

const isSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) formOpen.value = false
  },
)

function onOuterOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}

function onFormOpenUpdate(value: boolean): void {
  if (!value) {
    formOpen.value = false
    resetFormDirty()
  }
}

function openCreate(): void {
  editing.value = null
  form.name = ''
  form.code = ''
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
  captureFormDirty()
}

function openEdit(category: ContractCategory): void {
  editing.value = category
  form.name = category.name
  form.code = category.code ?? ''
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
  captureFormDirty()
}

function fieldMessage(key: string | undefined): string {
  if (!key) return ''
  return t(`contracts.validation.${key}`)
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return t('contracts.errors.generic')
  }
  const mapped = mapContractErrorCode(error.code)
  if (mapped !== 'generic') {
    return t(`contracts.errors.${mapped}`)
  }
  return error.message || t('contracts.errors.generic')
}

async function submitForm(): Promise<void> {
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const validation = validateCategoryForm(form)
  if (validation.name) {
    fieldErrors.name = fieldMessage(validation.name)
    return
  }
  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        id: editing.value.id,
        payload: {
          name: form.name.trim(),
        },
      })
      toast.success(t('contracts.toasts.categoryUpdated'))
    } else {
      await createMutation.mutateAsync({
        name: form.name.trim(),
        code: form.code.trim() || null,
      })
      toast.success(t('contracts.toasts.categoryCreated'))
    }
    formOpen.value = false
    resetFormDirty()
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function toggleStatus(category: ContractCategory): Promise<void> {
  const deactivating = category.is_active
  const confirmed = await confirm({
    title: deactivating
      ? t('contracts.confirmCategoryDeactivateTitle')
      : t('contracts.confirmCategoryActivateTitle'),
    message: deactivating
      ? t('contracts.confirmCategoryDeactivateBody')
      : t('contracts.confirmCategoryActivateBody'),
    confirmLabel: deactivating
      ? t('contracts.confirmCategoryDeactivateCta')
      : t('contracts.confirmCategoryActivateCta'),
    cancelLabel: t('contracts.cancel'),
    variant: deactivating ? 'warning' : 'primary',
  })
  if (!confirmed) return
  try {
    if (deactivating) {
      await deactivateMutation.mutateAsync(category.id)
      toast.success(t('contracts.toasts.categoryDeactivated'))
    } else {
      await activateMutation.mutateAsync(category.id)
      toast.success(t('contracts.toasts.categoryActivated'))
    }
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function confirmDelete(category: ContractCategory): Promise<void> {
  const confirmed = await confirm({
    title: t('contracts.confirmCategoryDeleteTitle'),
    message: t('contracts.confirmCategoryDeleteBody'),
    confirmLabel: t('contracts.confirmCategoryDeleteCta'),
    cancelLabel: t('contracts.cancel'),
    variant: 'danger',
  })
  if (!confirmed) return
  try {
    await deleteMutation.mutateAsync(category.id)
    toast.success(t('contracts.toasts.categoryDeleted'))
  } catch (error) {
    if (error instanceof ApiError && error.code === 'CONTRACT_CATEGORY_IN_USE') {
      toast.error(t('contracts.errors.CONTRACT_CATEGORY_IN_USE'))
      return
    }
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="t('contracts.categoriesTitle')"
    :description="t('contracts.categoriesSubtitle')"
    size="md"
    hide-default-footer
    @update:open="onOuterOpenUpdate"
  >
    <div class="mb-4 flex flex-wrap items-center gap-3 border-b border-brand-border pb-3 -mt-1">
      <input
        v-model="search"
        type="search"
        class="h-10 min-w-40 flex-1 rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
        :placeholder="t('contracts.categoriesSearch')"
      />
      <PermissionGuard permission="contracts.update">
        <button
          type="button"
          class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-brand-primary-dark px-3 text-sm font-semibold text-white"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('contracts.addCategory') }}
        </button>
      </PermissionGuard>
    </div>

    <div v-if="isLoading" class="py-10 text-center text-sm text-brand-text-muted">
      {{ t('contracts.categoriesLoading') }}
    </div>
    <div v-else-if="isError" class="py-10 text-center">
      <p class="text-sm text-red-700">{{ t('contracts.errors.categoriesLoad') }}</p>
      <button
        type="button"
        class="mt-2 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('contracts.retry') }}
      </button>
    </div>
    <div
      v-else-if="categories.length === 0"
      class="py-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('contracts.categoriesEmpty') }}
    </div>
    <ul v-else class="space-y-2">
      <li
        v-for="category in categories"
        :key="category.id"
        class="flex items-center justify-between gap-3 rounded-xl border border-brand-border px-3.5 py-3"
      >
        <div class="min-w-0">
          <p class="truncate text-sm font-semibold text-brand-text">{{ category.name }}</p>
          <p v-if="category.code" class="mt-0.5 font-mono text-xs text-brand-text-muted">
            {{ category.code }}
          </p>
        </div>
        <div class="flex shrink-0 items-center gap-1">
          <span
            class="me-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
            :class="
              category.is_active
                ? 'bg-emerald-50 text-emerald-800'
                : 'bg-neutral-100 text-neutral-600'
            "
          >
            {{
              category.is_active
                ? t('contracts.categoryActive')
                : t('contracts.categoryInactive')
            }}
          </span>
          <PermissionGuard permission="contracts.update">
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-primary-dark hover:bg-brand-primary-soft"
              :aria-label="t('contracts.actions.edit')"
              @click="openEdit(category)"
            >
              <Pencil class="h-3.5 w-3.5" />
            </button>
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-brand-bg"
              :aria-label="
                category.is_active
                  ? t('contracts.actions.deactivate')
                  : t('contracts.actions.activate')
              "
              @click="toggleStatus(category)"
            >
              <PowerOff v-if="category.is_active" class="h-3.5 w-3.5 text-amber-700" />
              <Power v-else class="h-3.5 w-3.5 text-emerald-700" />
            </button>
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-700 hover:bg-red-50"
              :aria-label="t('contracts.actions.delete')"
              @click="confirmDelete(category)"
            >
              <Trash2 class="h-3.5 w-3.5" />
            </button>
          </PermissionGuard>
        </div>
      </li>
    </ul>
  </AppFormDrawer>

  <AppFormDrawer
    :open="formOpen"
    :title="editing ? t('contracts.editCategoryTitle') : t('contracts.createCategoryTitle')"
    size="sm"
    :dirty="formDirty"
    :submitting="isSubmitting"
    :cancel-label="t('contracts.cancel')"
    :submit-label="t('contracts.save')"
    @update:open="onFormOpenUpdate"
    @submit="submitForm"
  >
    <label class="block">
      <span class="mb-1.5 block text-sm font-medium">{{
        t('contracts.fields.categoryName')
      }}</span>
      <input
        v-model="form.name"
        type="text"
        class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary"
        :disabled="isSubmitting"
      />
      <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">
        {{ fieldErrors.name }}
      </p>
    </label>
    <label v-if="!editing" class="block">
      <span class="mb-1.5 block text-sm font-medium">{{
        t('contracts.fields.categoryCode')
      }}</span>
      <input
        v-model="form.code"
        type="text"
        class="h-11 w-full rounded-xl border border-brand-border px-3 font-mono text-sm uppercase outline-none focus:border-brand-primary"
        :disabled="isSubmitting"
      />
    </label>
    <p
      v-if="formError"
      class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ formError }}
    </p>
  </AppFormDrawer>
</template>