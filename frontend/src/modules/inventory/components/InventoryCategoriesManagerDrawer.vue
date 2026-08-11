<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, Pencil, Plus, Power, PowerOff, Trash2, X } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
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

const isSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) formOpen.value = false
  },
)

function openCreate(): void {
  editing.value = null
  form.name = ''
  form.description = ''
  form.is_active = true
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
}

function openEdit(category: InventoryCategory): void {
  editing.value = category
  form.name = category.name
  form.description = category.description ?? ''
  form.is_active = category.is_active
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
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
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50" role="presentation">
      <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
      <aside
        class="absolute inset-y-0 start-0 flex w-full max-w-[520px] flex-col bg-brand-surface shadow-xl"
        role="dialog"
        aria-modal="true"
        @click.stop
      >
        <header class="flex items-start justify-between border-b border-brand-border px-5 py-4">
          <div>
            <h2 class="text-lg font-bold">{{ t('inventory.categories.title') }}</h2>
            <p class="mt-1 text-sm text-brand-text-secondary">{{ t('inventory.categories.subtitle') }}</p>
          </div>
          <button
            type="button"
            class="rounded-lg p-2 text-brand-text-muted hover:bg-brand-bg"
            :aria-label="t('inventory.closeDrawer')"
            @click="emit('close')"
          >
            <X class="h-5 w-5" />
          </button>
        </header>

        <div class="border-b border-brand-border px-5 py-3">
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

        <div class="flex-1 overflow-y-auto px-5 py-4">
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
        </div>

        <div
          v-if="formOpen"
          class="absolute inset-0 z-10 flex items-end bg-black/20 sm:items-center sm:justify-center"
        >
          <div class="w-full rounded-t-2xl bg-brand-surface p-5 shadow-xl sm:max-w-md sm:rounded-2xl">
            <h3 class="text-lg font-bold">
              {{ editing ? t('inventory.categories.editTitle') : t('inventory.categories.createTitle') }}
            </h3>
            <div class="mt-4 space-y-3">
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
            </div>
            <div class="mt-5 flex justify-end gap-2">
              <button
                type="button"
                class="rounded-xl px-4 py-2 text-sm"
                :disabled="isSubmitting"
                @click="formOpen = false"
              >
                {{ t('inventory.cancel') }}
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-primary-dark px-4 py-2 text-sm font-semibold text-white"
                :disabled="isSubmitting"
                @click="submitForm"
              >
                <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                {{ t('inventory.save') }}
              </button>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </Teleport>
</template>
