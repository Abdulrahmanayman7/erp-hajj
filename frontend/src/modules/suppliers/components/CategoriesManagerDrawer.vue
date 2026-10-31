<script setup lang="ts">
import { computed, nextTick, onUnmounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, Pencil, Plus, Power, PowerOff, Trash2, X } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useToast } from '@/shared/composables/useToast'

import {
  useCreateSupplierCategoryMutation,
  useDeactivateSupplierCategoryMutation,
  useDeleteSupplierCategoryMutation,
  useUpdateSupplierCategoryMutation,
} from '../mutations/useCategoryMutations'
import { useSupplierCategoriesQuery } from '../queries/useCategoriesQuery'
import type { CategoryFormState, SupplierCategory } from '../types/categories'
import { mapSupplierErrorCode, validateCategoryForm } from '../validation/supplierValidation'

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

const { data, isLoading, isError, refetch } = useSupplierCategoriesQuery(queryParams, {
  enabled: computed(() => props.open),
})

const createMutation = useCreateSupplierCategoryMutation()
const updateMutation = useUpdateSupplierCategoryMutation()
const deactivateMutation = useDeactivateSupplierCategoryMutation()
const deleteMutation = useDeleteSupplierCategoryMutation()

const categories = computed(() => data.value?.data ?? [])
const editing = ref<SupplierCategory | null>(null)
const formOpen = ref(false)
const formError = ref('')
const form = reactive<CategoryFormState>({ name: '', code: '' })
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
  form.code = ''
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
}

function openEdit(category: SupplierCategory): void {
  editing.value = category
  form.name = category.name
  form.code = category.code ?? ''
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
}

function closeForm(): void {
  formOpen.value = false
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

async function submitForm(): Promise<void> {
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const validation = validateCategoryForm(form)
  if (validation.name) {
    fieldErrors.name = t(`suppliers.validation.${validation.name}`)
    return
  }
  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        id: editing.value.id,
        payload: { name: form.name.trim() },
      })
      toast.success(t('suppliers.toasts.categoryUpdated'))
    } else {
      await createMutation.mutateAsync({
        name: form.name.trim(),
        code: form.code.trim() || null,
      })
      toast.success(t('suppliers.toasts.categoryCreated'))
    }
    closeForm()
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function toggleStatus(category: SupplierCategory): Promise<void> {
  const deactivating = category.is_active
  const confirmed = await confirm({
    title: deactivating
      ? t('suppliers.confirmCategoryDeactivateTitle')
      : t('suppliers.confirmCategoryActivateTitle'),
    message: deactivating
      ? t('suppliers.confirmCategoryDeactivateBody')
      : t('suppliers.confirmCategoryActivateBody'),
    confirmLabel: deactivating
      ? t('suppliers.confirmCategoryDeactivateCta')
      : t('suppliers.confirmCategoryActivateCta'),
    cancelLabel: t('suppliers.cancel'),
    variant: deactivating ? 'warning' : 'primary',
  })
  if (!confirmed) return
  try {
    if (deactivating) {
      await deactivateMutation.mutateAsync(category.id)
      toast.success(t('suppliers.toasts.categoryDeactivated'))
    } else {
      await updateMutation.mutateAsync({ id: category.id, payload: { is_active: true } })
      toast.success(t('suppliers.toasts.categoryActivated'))
    }
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function confirmDelete(category: SupplierCategory): Promise<void> {
  const confirmed = await confirm({
    title: t('suppliers.confirmCategoryDeleteTitle'),
    message: t('suppliers.confirmCategoryDeleteBody'),
    confirmLabel: t('suppliers.confirmCategoryDeleteCta'),
    cancelLabel: t('suppliers.cancel'),
    variant: 'danger',
  })
  if (!confirmed) return
  try {
    await deleteMutation.mutateAsync(category.id)
    toast.success(t('suppliers.toasts.categoryDeleted'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="ctr-drawer">
      <div v-if="open" class="fixed inset-0 z-[70] flex justify-start">
        <button
          type="button"
          class="absolute inset-0 bg-black/40"
          :aria-label="t('suppliers.closeDrawer')"
          @click="emit('close')"
        />
        <aside
          class="relative z-10 flex h-full w-full max-w-xl flex-col bg-brand-surface shadow-2xl"
          role="dialog"
          aria-modal="true"
        >
          <header class="flex items-start justify-between border-b border-brand-border px-5 py-4">
            <div>
              <h2 class="text-lg font-bold text-brand-text">{{ t('suppliers.categoriesTitle') }}</h2>
              <p class="mt-1 text-sm text-brand-text-secondary">{{ t('suppliers.categoriesSubtitle') }}</p>
            </div>
            <button type="button" class="rounded-lg p-1 hover:bg-brand-bg" @click="emit('close')">
              <X class="h-5 w-5" />
            </button>
          </header>

          <div class="border-b border-brand-border px-5 py-3">
            <input
              v-model="search"
              type="search"
              class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
              :placeholder="t('suppliers.categoriesSearch')"
            />
          </div>

          <div class="flex-1 overflow-y-auto px-5 py-4">
            <PermissionGuard permission="suppliers.update">
              <button
                type="button"
                class="mb-4 inline-flex items-center gap-2 rounded-xl bg-brand-primary px-3 py-2 text-sm font-semibold text-white"
                @click="openCreate"
              >
                <Plus class="h-4 w-4" />
                {{ t('suppliers.addCategory') }}
              </button>
            </PermissionGuard>

            <div v-if="isLoading" class="py-8 text-center text-sm text-brand-text-muted">
              {{ t('suppliers.categoriesLoading') }}
            </div>
            <div v-else-if="isError" class="py-8 text-center">
              <p class="text-sm text-red-600">{{ t('suppliers.errors.generic') }}</p>
              <button type="button" class="mt-2 text-sm font-semibold text-brand-primary" @click="refetch">
                {{ t('suppliers.retry') }}
              </button>
            </div>
            <div v-else-if="categories.length === 0" class="py-8 text-center text-sm text-brand-text-muted">
              {{ t('suppliers.categoriesEmpty') }}
            </div>
            <ul v-else class="space-y-2">
              <li
                v-for="category in categories"
                :key="category.id"
                class="flex items-center justify-between gap-3 rounded-xl border border-brand-border px-3 py-3"
              >
                <div class="min-w-0">
                  <p class="font-semibold text-brand-text">{{ category.name }}</p>
                  <p class="text-xs text-brand-text-muted">
                    {{ category.code || '—' }}
                    ·
                    {{
                      category.is_active ? t('suppliers.categoryActive') : t('suppliers.categoryInactive')
                    }}
                  </p>
                </div>
                <PermissionGuard permission="suppliers.update">
                  <div class="flex shrink-0 items-center gap-1">
                    <button
                      type="button"
                      class="rounded-lg p-2 hover:bg-brand-bg"
                      :aria-label="t('suppliers.editCategoryTitle')"
                      @click="openEdit(category)"
                    >
                      <Pencil class="h-4 w-4" />
                    </button>
                    <button
                      type="button"
                      class="rounded-lg p-2 hover:bg-brand-bg"
                      @click="toggleStatus(category)"
                    >
                      <PowerOff v-if="category.is_active" class="h-4 w-4" />
                      <Power v-else class="h-4 w-4" />
                    </button>
                    <button
                      type="button"
                      class="rounded-lg p-2 text-red-600 hover:bg-red-50"
                      @click="confirmDelete(category)"
                    >
                      <Trash2 class="h-4 w-4" />
                    </button>
                  </div>
                </PermissionGuard>
              </li>
            </ul>
          </div>

          <div
            v-if="formOpen"
            class="absolute inset-0 z-20 flex items-end bg-black/30 sm:items-center sm:justify-center"
          >
            <div class="w-full max-w-md rounded-t-2xl bg-brand-surface p-5 shadow-xl sm:rounded-2xl">
              <h3 class="text-base font-bold text-brand-text">
                {{ editing ? t('suppliers.editCategoryTitle') : t('suppliers.createCategoryTitle') }}
              </h3>
              <p v-if="formError" class="mt-2 text-sm text-red-600">{{ formError }}</p>
              <div class="mt-4 space-y-3">
                <div>
                  <label class="mb-1 block text-sm font-semibold">{{ t('suppliers.fields.categoryName') }}</label>
                  <input v-model="form.name" type="text" class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm" />
                  <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
                </div>
                <div v-if="!editing">
                  <label class="mb-1 block text-sm font-semibold">{{ t('suppliers.fields.categoryCode') }}</label>
                  <input v-model="form.code" type="text" class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm" />
                </div>
              </div>
              <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="rounded-xl border px-4 py-2 text-sm font-semibold" @click="closeForm">
                  {{ t('suppliers.cancel') }}
                </button>
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                  :disabled="isSubmitting"
                  @click="submitForm"
                >
                  <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                  {{ t('suppliers.save') }}
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
.ctr-drawer-enter-active,
.ctr-drawer-leave-active {
  transition: opacity 0.2s ease;
}
.ctr-drawer-enter-from,
.ctr-drawer-leave-to {
  opacity: 0;
}
</style>
