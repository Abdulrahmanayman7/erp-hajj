<script setup lang="ts">
import { computed, nextTick, onUnmounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, Pencil, Plus, Power, PowerOff, Trash2, X } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
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

const isSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

let previouslyFocused: HTMLElement | null = null

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.open && !isSubmitting.value && !formOpen.value) {
    event.preventDefault()
    emit('close')
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      previouslyFocused =
        document.activeElement instanceof HTMLElement ? document.activeElement : null
      document.addEventListener('keydown', onKeydown)
      await nextTick()
      return
    }
    formOpen.value = false
    document.removeEventListener('keydown', onKeydown)
    await nextTick()
    previouslyFocused?.focus?.()
    previouslyFocused = null
  },
)

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})

function openCreate(): void {
  editing.value = null
  form.name = ''
  form.description = ''
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
}

function openEdit(category: DocumentCategory): void {
  editing.value = category
  form.name = category.name
  form.description = category.description ?? ''
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
}

function closeForm(): void {
  formOpen.value = false
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
  <Teleport to="body">
    <Transition name="doc-cat-drawer">
      <div v-if="open" class="doc-cat-drawer-root fixed inset-0 z-50" role="presentation">
        <div
          class="absolute inset-0 bg-[rgba(15,23,20,0.32)]"
          aria-hidden="true"
          @click="emit('close')"
        />
        <aside
          class="doc-cat-drawer-panel absolute inset-y-0 start-0 flex h-dvh w-full max-w-[520px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
          role="dialog"
          aria-modal="true"
          :aria-label="t('documents.categoriesTitle')"
          @click.stop
        >
          <header class="flex shrink-0 items-start justify-between gap-3 border-b border-brand-border px-4 py-4">
            <div>
              <h2 class="text-lg font-bold">{{ t('documents.categoriesTitle') }}</h2>
              <p class="mt-1 text-sm text-brand-text-secondary">{{ t('documents.categoriesSubtitle') }}</p>
            </div>
            <button
              type="button"
              class="rounded-lg p-2 text-brand-text-muted hover:bg-brand-bg"
              :aria-label="t('documents.closeDrawer')"
              @click="emit('close')"
            >
              <X class="h-5 w-5" />
            </button>
          </header>

          <div class="flex items-center justify-between gap-3 border-b border-brand-border px-4 py-3">
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

          <div class="flex-1 overflow-y-auto px-4 py-4">
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
          </div>

          <div
            v-if="formOpen"
            class="absolute inset-0 z-10 flex items-end bg-[rgba(15,23,20,0.2)] sm:items-center sm:justify-center"
          >
            <div
              class="w-full rounded-t-2xl bg-brand-surface p-4 shadow-xl sm:max-w-md sm:rounded-2xl sm:p-5"
              style="padding-bottom: max(16px, env(safe-area-inset-bottom))"
            >
              <h3 class="text-lg font-bold">
                {{ editing ? t('documents.editCategoryTitle') : t('documents.createCategoryTitle') }}
              </h3>
              <div class="mt-4 space-y-3">
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
              </div>
              <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                  type="button"
                  class="inline-flex h-11 items-center justify-center rounded-xl px-4 text-sm"
                  :disabled="isSubmitting"
                  @click="closeForm"
                >
                  {{ t('documents.cancel') }}
                </button>
                <button
                  type="button"
                  class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-60"
                  :disabled="isSubmitting"
                  @click="submitForm"
                >
                  <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                  {{ t('documents.save') }}
                </button>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.doc-cat-drawer-enter-active,
.doc-cat-drawer-leave-active {
  transition: opacity 0.2s ease;
}
.doc-cat-drawer-enter-active .doc-cat-drawer-panel,
.doc-cat-drawer-leave-active .doc-cat-drawer-panel {
  transition: transform 0.25s ease;
}
.doc-cat-drawer-enter-from,
.doc-cat-drawer-leave-to {
  opacity: 0;
}
.doc-cat-drawer-enter-from .doc-cat-drawer-panel,
.doc-cat-drawer-leave-to .doc-cat-drawer-panel {
  transform: translateX(100%);
}
[dir='ltr'] .doc-cat-drawer-enter-from .doc-cat-drawer-panel,
[dir='ltr'] .doc-cat-drawer-leave-to .doc-cat-drawer-panel {
  transform: translateX(-100%);
}
</style>
