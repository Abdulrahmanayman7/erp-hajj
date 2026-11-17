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
  useCreateInventoryCategoryMutation,
  useDeleteInventoryCategoryMutation,
  useUpdateInventoryCategoryMutation,
} from '../mutations/useCategoryMutations'
import { useInventoryCategoriesQuery } from '../queries/useCategoriesQuery'
import type { InventoryCategory, InventoryCategoryFormState } from '../types/categories'
import {
  mapInventoryErrorCode,
  validateInventoryCategoryForm,
} from '../validation/inventoryValidation'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()

const { data, isLoading, isError, refetch } = useInventoryCategoriesQuery(
  {},
  { enabled: computed(() => props.open) },
)

const createMutation = useCreateInventoryCategoryMutation()
const updateMutation = useUpdateInventoryCategoryMutation()
const deleteMutation = useDeleteInventoryCategoryMutation()

const categories = computed(() => data.value ?? [])
const editing = ref<InventoryCategory | null>(null)
const formOpen = ref(false)
const formError = ref('')
const form = reactive<InventoryCategoryFormState>({ name: '', description: '', is_active: true })
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
  form.description = ''
  form.is_active = true
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
  captureFormDirty()
}

function openEdit(category: InventoryCategory): void {
  editing.value = category
  form.name = category.name
  form.description = category.description ?? ''
  form.is_active = category.is_active
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
  captureFormDirty()
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('inventory.errors.generic')
  const mapped = mapInventoryErrorCode(error.code)
  if (mapped !== 'generic') return t(`inventory.errors.${mapped}`)
  return error.message || t('inventory.errors.generic')
}

async function submitForm(): Promise<void> {
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const validation = validateInventoryCategoryForm(form)
  if (validation.name) {
    fieldErrors.name = validation.name
    return
  }
  try {
    const payload = {
      name: form.name.trim(),
      description: form.description.trim() || null,
      is_active: form.is_active,
    }
    if (editing.value) {
      await updateMutation.mutateAsync({ id: editing.value.id, payload })
      toast.success(t('inventory.toasts.categoryUpdated'))
    } else {
      await createMutation.mutateAsync(payload)
      toast.success(t('inventory.toasts.categoryCreated'))
    }
    formOpen.value = false
    resetFormDirty()
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function toggleActive(category: InventoryCategory): Promise<void> {
  const next = !category.is_active
  const ok = await confirm({
    title: next
      ? t('inventory.confirm.activateCategory.title')
      : t('inventory.confirm.deactivateCategory.title'),
    message: next
      ? t('inventory.confirm.activateCategory.body')
      : t('inventory.confirm.deactivateCategory.body'),
    confirmLabel: next ? t('inventory.actions.activate') : t('inventory.actions.deactivate'),
    variant: 'warning',
  })
  if (!ok) return
  try {
    await updateMutation.mutateAsync({ id: category.id, payload: { is_active: next } })
    toast.success(
      next ? t('inventory.toasts.categoryActivated') : t('inventory.toasts.categoryDeactivated'),
    )
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function confirmDelete(category: InventoryCategory): Promise<void> {
  const ok = await confirm({
    title: t('inventory.confirm.deleteCategory.title'),
    message: t('inventory.confirm.deleteCategory.body'),
    confirmLabel: t('inventory.actions.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync(category.id)
    toast.success(t('inventory.toasts.categoryDeleted'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="t('inventory.categories.title')"
    :description="t('inventory.categories.subtitle')"
    size="md"
    hide-default-footer
    @update:open="onOuterOpenUpdate"
  >
    <div class="border-b border-brand-border pb-3 -mt-1 mb-4">
      <PermissionGuard permission="inventory.manage_items">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-brand-primary-dark px-3 py-2 text-sm font-semibold text-white"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('inventory.categories.add') }}
        </button>
      </PermissionGuard>
    </div>

    <div v-if="isLoading" class="py-10 text-center text-sm">{{ t('inventory.categories.loading') }}</div>
    <div v-else-if="isError" class="py-10 text-center text-sm">
      <p>{{ t('inventory.errors.loadCategories') }}</p>
      <button type="button" class="mt-2 underline" @click="() => refetch()">
        {{ t('inventory.retry') }}
      </button>
    </div>
    <div v-else-if="!categories.length" class="py-10 text-center text-sm text-brand-text-muted">
      {{ t('inventory.categories.empty') }}
    </div>
    <ul v-else class="space-y-2">
      <li
        v-for="category in categories"
        :key="category.id"
        class="flex items-center justify-between gap-3 rounded-xl border border-brand-border px-3 py-3"
      >
        <div class="min-w-0">
          <p class="font-semibold">{{ category.name }}</p>
          <p v-if="category.description" class="truncate text-xs text-brand-text-muted">
            {{ category.description }}
          </p>
          <span
            class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
            :class="
              category.is_active
                ? 'bg-emerald-50 text-emerald-800'
                : 'bg-neutral-100 text-neutral-600'
            "
          >
            {{ category.is_active ? t('inventory.status.active') : t('inventory.status.inactive') }}
          </span>
        </div>
        <div class="flex shrink-0 items-center gap-1">
          <PermissionGuard permission="inventory.manage_items">
            <button type="button" class="rounded-lg p-2 hover:bg-brand-bg" @click="openEdit(category)">
              <Pencil class="h-4 w-4" />
            </button>
            <button type="button" class="rounded-lg p-2 hover:bg-brand-bg" @click="toggleActive(category)">
              <PowerOff v-if="category.is_active" class="h-4 w-4" />
              <Power v-else class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="rounded-lg p-2 text-red-700 hover:bg-red-50"
              @click="confirmDelete(category)"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </PermissionGuard>
        </div>
      </li>
    </ul>
  </AppFormDrawer>

  <AppFormDrawer
    :open="formOpen"
    :title="editing ? t('inventory.categories.editTitle') : t('inventory.categories.createTitle')"
    size="sm"
    :dirty="formDirty"
    :submitting="isSubmitting"
    :cancel-label="t('inventory.cancel')"
    :submit-label="t('inventory.save')"
    @update:open="onFormOpenUpdate"
    @submit="submitForm"
  >
    <label class="block">
      <span class="text-sm font-medium">{{ t('inventory.fields.categoryName') }}</span>
      <input
        v-model="form.name"
        type="text"
        class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3"
        :disabled="isSubmitting"
      />
      <p v-if="fieldErrors.name" class="mt-1 text-sm text-red-700">
        {{ t(`inventory.validation.${fieldErrors.name}`) }}
      </p>
    </label>
    <label class="block">
      <span class="text-sm font-medium">{{ t('inventory.fields.categoryDescription') }}</span>
      <textarea
        v-model="form.description"
        rows="3"
        class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2"
        :disabled="isSubmitting"
      />
    </label>
    <p v-if="formError" class="text-sm text-red-700">{{ formError }}</p>
  </AppFormDrawer>
</template>