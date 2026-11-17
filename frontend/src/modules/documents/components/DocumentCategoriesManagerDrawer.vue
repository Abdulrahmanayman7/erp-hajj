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
  useCreateDocumentCategoryMutation,
  useDeleteDocumentCategoryMutation,
  useUpdateDocumentCategoryMutation,
} from '../mutations/useCategoryMutations'
import { useDocumentCategoriesQuery } from '../queries/useCategoriesQuery'
import type { DocumentCategory, DocumentCategoryFormState } from '../types/categories'
import { mapDocumentErrorCode, validateDocumentCategoryForm } from '../validation/documentValidation'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
}>()

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()

const { data, isLoading, isError, refetch } = useDocumentCategoriesQuery(
  {},
  { enabled: computed(() => props.open) },
)

const createMutation = useCreateDocumentCategoryMutation()
const updateMutation = useUpdateDocumentCategoryMutation()
const deleteMutation = useDeleteDocumentCategoryMutation()

const categories = computed(() => data.value ?? [])
const editing = ref<DocumentCategory | null>(null)
const formOpen = ref(false)
const formError = ref('')
const form = reactive<DocumentCategoryFormState>({ name: '', description: '' })
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
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
  captureFormDirty()
}

function openEdit(category: DocumentCategory): void {
  editing.value = category
  form.name = category.name
  form.description = category.description ?? ''
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
  captureFormDirty()
}

function fieldMessage(key: string | undefined): string {
  if (!key) return ''
  return t(`documents.validation.${key}`)
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return t('documents.errors.generic')
  }
  const mapped = mapDocumentErrorCode(error.code)
  if (mapped !== 'generic') {
    return t(`documents.errors.${mapped}`)
  }
  return error.message || t('documents.errors.generic')
}

async function submitForm(): Promise<void> {
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const validation = validateDocumentCategoryForm(form)
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
          description: form.description.trim() || null,
        },
      })
      toast.success(t('documents.toasts.categoryUpdated'))
    } else {
      await createMutation.mutateAsync({
        name: form.name.trim(),
        description: form.description.trim() || null,
      })
      toast.success(t('documents.toasts.categoryCreated'))
    }
    formOpen.value = false
    resetFormDirty()
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function toggleActive(category: DocumentCategory): Promise<void> {
  const next = !category.is_active
  const ok = await confirm({
    title: next ? t('documents.confirm.activateCategory.title') : t('documents.confirm.deactivateCategory.title'),
    message: next
      ? t('documents.confirm.activateCategory.body')
      : t('documents.confirm.deactivateCategory.body'),
    confirmLabel: next ? t('documents.actions.activate') : t('documents.actions.deactivate'),
    variant: 'warning',
  })
  if (!ok) return
  try {
    await updateMutation.mutateAsync({ id: category.id, payload: { is_active: next } })
    toast.success(
      next ? t('documents.toasts.categoryActivated') : t('documents.toasts.categoryDeactivated'),
    )
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function confirmDelete(category: DocumentCategory): Promise<void> {
  const ok = await confirm({
    title: t('documents.confirm.deleteCategory.title'),
    message: t('documents.confirm.deleteCategory.body'),
    confirmLabel: t('documents.actions.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync(category.id)
    toast.success(t('documents.toasts.categoryDeleted'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="t('documents.categoriesTitle')"
    :description="t('documents.categoriesSubtitle')"
    size="md"
    hide-default-footer
    @update:open="onOuterOpenUpdate"
  >
    <div class="border-b border-brand-border pb-3 -mt-1 mb-4">
      <PermissionGuard permission="documents.manage_categories">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-brand-primary-dark px-3 py-2 text-sm font-semibold text-white"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('documents.addCategory') }}
        </button>
      </PermissionGuard>
    </div>

    <div v-if="isLoading" class="py-10 text-center text-sm">{{ t('documents.categoriesLoading') }}</div>
    <div v-else-if="isError" class="py-10 text-center text-sm">
      <p>{{ t('documents.errors.loadCategories') }}</p>
      <button type="button" class="mt-2 underline" @click="() => refetch()">
        {{ t('documents.retry') }}
      </button>
    </div>
    <div v-else-if="!categories.length" class="py-10 text-center text-sm text-brand-text-muted">
      {{ t('documents.categoriesEmpty') }}
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
            {{ category.is_active ? t('documents.categoryActive') : t('documents.categoryInactive') }}
          </span>
        </div>
        <div class="flex shrink-0 items-center gap-1">
          <PermissionGuard permission="documents.manage_categories">
            <button
              type="button"
              class="rounded-lg p-2 hover:bg-brand-bg"
              :aria-label="t('documents.actions.edit')"
              @click="openEdit(category)"
            >
              <Pencil class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="rounded-lg p-2 hover:bg-brand-bg"
              :aria-label="
                category.is_active
                  ? t('documents.actions.deactivate')
                  : t('documents.actions.activate')
              "
              @click="toggleActive(category)"
            >
              <PowerOff v-if="category.is_active" class="h-4 w-4" />
              <Power v-else class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="rounded-lg p-2 text-red-700 hover:bg-red-50"
              :aria-label="t('documents.actions.delete')"
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
    :title="editing ? t('documents.editCategoryTitle') : t('documents.createCategoryTitle')"
    size="sm"
    :dirty="formDirty"
    :submitting="isSubmitting"
    :cancel-label="t('documents.cancel')"
    :submit-label="t('documents.save')"
    @update:open="onFormOpenUpdate"
    @submit="submitForm"
  >
    <label class="block space-y-1.5">
      <span class="text-sm font-medium">{{ t('documents.fields.categoryName') }}</span>
      <input
        v-model="form.name"
        type="text"
        class="h-11 w-full rounded-xl border border-brand-border px-3"
        :disabled="isSubmitting"
      />
      <p v-if="fieldErrors.name" class="text-sm text-red-700">{{ fieldErrors.name }}</p>
    </label>
    <label class="block space-y-1.5">
      <span class="text-sm font-medium">{{ t('documents.fields.categoryDescription') }}</span>
      <textarea
        v-model="form.description"
        rows="3"
        class="w-full rounded-xl border border-brand-border px-3 py-2"
        :disabled="isSubmitting"
      />
    </label>
    <p v-if="formError" class="text-sm text-red-700">{{ formError }}</p>
  </AppFormDrawer>
</template>