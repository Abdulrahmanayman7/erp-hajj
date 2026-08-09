<script setup lang="ts">
import { computed, nextTick, onUnmounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, Pencil, Plus, Power, PowerOff, Trash2, X } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
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

function openEdit(category: ContractCategory): void {
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
    closeForm()
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
  <Teleport to="body">
    <Transition name="cat-drawer">
      <div v-if="open" class="cat-drawer-root fixed inset-0 z-50" role="presentation">
        <div
          class="absolute inset-0 bg-[rgba(15,23,20,0.32)]"
          aria-hidden="true"
          @click="emit('close')"
        />

        <aside
          class="cat-drawer-panel absolute inset-y-0 start-0 flex h-dvh w-full max-w-[520px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
          role="dialog"
          aria-modal="true"
          :aria-label="t('contracts.categoriesTitle')"
          @click.stop
        >
          <header
            class="flex shrink-0 items-start justify-between gap-4 border-b border-brand-border px-6 py-5"
          >
            <div>
              <h3 class="text-[21px] font-bold text-brand-text">
                {{ t('contracts.categoriesTitle') }}
              </h3>
              <p class="mt-1.5 text-[13px] text-brand-text-secondary">
                {{ t('contracts.categoriesSubtitle') }}
              </p>
            </div>
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-[10px] text-brand-text-secondary hover:bg-brand-bg"
              :aria-label="t('contracts.closeDrawer')"
              @click="emit('close')"
            >
              <X class="h-4 w-4" />
            </button>
          </header>

          <div class="flex flex-wrap items-center gap-3 border-b border-brand-border px-6 py-3">
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

          <div class="flex-1 overflow-y-auto px-6 py-4">
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
          </div>
        </aside>

        <div
          v-if="formOpen"
          class="absolute inset-0 z-10 flex items-center justify-center bg-[rgba(15,23,20,0.28)] p-4"
        >
          <div class="w-full max-w-sm rounded-2xl bg-brand-surface p-5 shadow-xl">
            <h4 class="text-base font-semibold text-brand-text">
              {{
                editing ? t('contracts.editCategoryTitle') : t('contracts.createCategoryTitle')
              }}
            </h4>
            <div class="mt-4 space-y-3">
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
            </div>
            <div class="mt-5 flex justify-end gap-2">
              <button
                type="button"
                class="rounded-xl px-4 py-2 text-sm font-semibold text-brand-text-secondary"
                :disabled="isSubmitting"
                @click="closeForm"
              >
                {{ t('contracts.cancel') }}
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-primary-dark px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                :disabled="isSubmitting"
                @click="submitForm"
              >
                <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                <span>{{ t('contracts.save') }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.cat-drawer-enter-active .cat-drawer-panel,
.cat-drawer-leave-active .cat-drawer-panel {
  transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1);
}

.cat-drawer-enter-from .cat-drawer-panel,
.cat-drawer-leave-to .cat-drawer-panel {
  transform: translateX(100%);
}

[dir='ltr'] .cat-drawer-enter-from .cat-drawer-panel,
[dir='ltr'] .cat-drawer-leave-to .cat-drawer-panel {
  transform: translateX(-100%);
}
</style>
