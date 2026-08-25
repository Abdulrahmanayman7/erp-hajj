<script setup lang="ts">
import { computed, nextTick, onUnmounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, Pencil, Plus, Power, PowerOff, Trash2, X } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useToast } from '@/shared/composables/useToast'

import {
  useActivatePositionMutation,
  useCreatePositionMutation,
  useDeactivatePositionMutation,
  useDeletePositionMutation,
  useUpdatePositionMutation,
} from '../mutations/usePositionMutations'
import { usePositionsQuery } from '../queries/usePositionsQuery'
import type { Position, PositionFormState } from '../types/positions'
import { mapEmployeeErrorCode, validatePositionForm } from '../validation/employeeValidation'

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

const { data, isLoading, isError, refetch } = usePositionsQuery(queryParams, {
  enabled: computed(() => props.open),
})

const createMutation = useCreatePositionMutation()
const updateMutation = useUpdatePositionMutation()
const activateMutation = useActivatePositionMutation()
const deactivateMutation = useDeactivatePositionMutation()
const deleteMutation = useDeletePositionMutation()

const positions = computed(() => data.value?.data ?? [])
const editing = ref<Position | null>(null)
const formOpen = ref(false)
const formError = ref('')
const form = reactive<PositionFormState>({ name: '', code: '' })
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

function openEdit(position: Position): void {
  editing.value = position
  form.name = position.name
  form.code = position.code ?? ''
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
}

function closeForm(): void {
  formOpen.value = false
}

function fieldMessage(key: string | undefined): string {
  if (!key) return ''
  return t(`employees.validation.${key}`)
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return t('employees.errors.generic')
  }
  const mapped = mapEmployeeErrorCode(error.code)
  if (mapped !== 'generic') {
    return t(`employees.errors.${mapped}`)
  }
  return error.message || t('employees.errors.generic')
}

async function submitForm(): Promise<void> {
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const validation = validatePositionForm(form)
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
          code: form.code.trim() || null,
        },
      })
      toast.success(t('employees.toasts.positionUpdated'))
    } else {
      await createMutation.mutateAsync({
        name: form.name.trim(),
        code: form.code.trim() || null,
      })
      toast.success(t('employees.toasts.positionCreated'))
    }
    closeForm()
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function toggleStatus(position: Position): Promise<void> {
  const deactivating = position.is_active
  const confirmed = await confirm({
    title: deactivating
      ? t('employees.confirmPositionDeactivateTitle')
      : t('employees.confirmPositionActivateTitle'),
    message: deactivating
      ? t('employees.confirmPositionDeactivateBody')
      : t('employees.confirmPositionActivateBody'),
    confirmLabel: deactivating
      ? t('employees.confirmPositionDeactivateCta')
      : t('employees.confirmPositionActivateCta'),
    cancelLabel: t('employees.cancel'),
    variant: deactivating ? 'warning' : 'primary',
  })
  if (!confirmed) return
  try {
    if (deactivating) {
      await deactivateMutation.mutateAsync(position.id)
      toast.success(t('employees.toasts.positionDeactivated'))
    } else {
      await activateMutation.mutateAsync(position.id)
      toast.success(t('employees.toasts.positionActivated'))
    }
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function confirmDelete(position: Position): Promise<void> {
  const confirmed = await confirm({
    title: t('employees.confirmPositionDeleteTitle'),
    message: t('employees.confirmPositionDeleteBody'),
    confirmLabel: t('employees.confirmPositionDeleteCta'),
    cancelLabel: t('employees.cancel'),
    variant: 'danger',
  })
  if (!confirmed) return
  try {
    await deleteMutation.mutateAsync(position.id)
    toast.success(t('employees.toasts.positionDeleted'))
  } catch (error) {
    if (error instanceof ApiError && error.code === 'POSITION_IN_USE') {
      toast.error(t('employees.errors.POSITION_IN_USE'))
      return
    }
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="pos-drawer">
      <div v-if="open" class="pos-drawer-root fixed inset-0 z-50" role="presentation">
        <div
          class="absolute inset-0 bg-[rgba(15,23,20,0.32)]"
          aria-hidden="true"
          @click="emit('close')"
        />

        <aside
          class="pos-drawer-panel absolute inset-y-0 start-0 flex h-dvh w-full max-w-[520px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
          role="dialog"
          aria-modal="true"
          :aria-label="t('employees.positionsTitle')"
          @click.stop
        >
          <header
            class="flex shrink-0 items-start justify-between gap-4 border-b border-brand-border px-4 py-5 sm:px-6"
          >
            <div>
              <h3 class="text-[21px] font-bold text-brand-text">
                {{ t('employees.positionsTitle') }}
              </h3>
              <p class="mt-1.5 text-[13px] text-brand-text-secondary">
                {{ t('employees.positionsSubtitle') }}
              </p>
            </div>
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-[10px] text-brand-text-secondary hover:bg-brand-bg"
              :aria-label="t('employees.closeDrawer')"
              @click="emit('close')"
            >
              <X class="h-4 w-4" />
            </button>
          </header>

          <div class="flex flex-wrap items-center gap-3 border-b border-brand-border px-4 py-3 sm:px-6">
            <input
              v-model="search"
              type="search"
              class="h-10 min-w-40 flex-1 rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
              :placeholder="t('employees.positionsSearch')"
            />
            <PermissionGuard permission="positions.create">
              <button
                type="button"
                class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-brand-primary-dark px-3 text-sm font-semibold text-white"
                @click="openCreate"
              >
                <Plus class="h-4 w-4" />
                {{ t('employees.addPosition') }}
              </button>
            </PermissionGuard>
          </div>

          <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-6">
            <div v-if="isLoading" class="py-10 text-center text-sm text-brand-text-muted">
              {{ t('employees.positionsLoading') }}
            </div>
            <div v-else-if="isError" class="py-10 text-center">
              <p class="text-sm text-red-700">{{ t('employees.errors.positionsLoad') }}</p>
              <button
                type="button"
                class="mt-2 text-sm font-semibold text-brand-primary-dark underline"
                @click="() => refetch()"
              >
                {{ t('employees.retry') }}
              </button>
            </div>
            <div
              v-else-if="positions.length === 0"
              class="py-10 text-center text-sm text-brand-text-muted"
            >
              {{ t('employees.positionsEmpty') }}
            </div>
            <ul v-else class="space-y-2">
              <li
                v-for="position in positions"
                :key="position.id"
                class="flex items-center justify-between gap-3 rounded-xl border border-brand-border px-3.5 py-3"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-brand-text">{{ position.name }}</p>
                  <p v-if="position.code" class="mt-0.5 font-mono text-xs text-brand-text-muted">
                    {{ position.code }}
                  </p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                  <span
                    class="me-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                    :class="
                      position.is_active
                        ? 'bg-emerald-50 text-emerald-800'
                        : 'bg-neutral-100 text-neutral-600'
                    "
                  >
                    {{
                      position.is_active
                        ? t('employees.status.active')
                        : t('employees.status.inactive')
                    }}
                  </span>
                  <PermissionGuard permission="positions.update">
                    <button
                      type="button"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-primary-dark hover:bg-brand-primary-soft"
                      :aria-label="t('employees.actions.edit')"
                      @click="openEdit(position)"
                    >
                      <Pencil class="h-3.5 w-3.5" />
                    </button>
                    <button
                      type="button"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-brand-bg"
                      :aria-label="
                        position.is_active
                          ? t('employees.actions.deactivate')
                          : t('employees.actions.activate')
                      "
                      @click="toggleStatus(position)"
                    >
                      <PowerOff v-if="position.is_active" class="h-3.5 w-3.5 text-amber-700" />
                      <Power v-else class="h-3.5 w-3.5 text-emerald-700" />
                    </button>
                  </PermissionGuard>
                  <PermissionGuard permission="positions.delete">
                    <button
                      type="button"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-700 hover:bg-red-50"
                      :aria-label="t('employees.actions.delete')"
                      @click="confirmDelete(position)"
                    >
                      <Trash2 class="h-3.5 w-3.5" />
                    </button>
                  </PermissionGuard>
                </div>
              </li>
            </ul>
          </div>
        </aside>

        <!-- Nested create/edit panel -->
        <div
          v-if="formOpen"
          class="absolute inset-0 z-10 flex items-center justify-center bg-[rgba(15,23,20,0.28)] p-4"
        >
          <div
            class="w-full max-w-sm rounded-2xl bg-brand-surface p-4 shadow-xl sm:p-5"
            style="padding-bottom: max(16px, env(safe-area-inset-bottom))"
          >
            <h4 class="text-base font-semibold text-brand-text">
              {{ editing ? t('employees.editPositionTitle') : t('employees.createPositionTitle') }}
            </h4>
            <div class="mt-4 space-y-3">
              <label class="block">
                <span class="mb-1.5 block text-sm font-medium">{{
                  t('employees.fields.positionName')
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
              <label class="block">
                <span class="mb-1.5 block text-sm font-medium">{{
                  t('employees.fields.positionCode')
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
            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <button
                type="button"
                class="inline-flex h-11 items-center justify-center rounded-xl px-4 text-sm font-semibold text-brand-text-secondary"
                :disabled="isSubmitting"
                @click="closeForm"
              >
                {{ t('employees.cancel') }}
              </button>
              <button
                type="button"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-60"
                :disabled="isSubmitting"
                @click="submitForm"
              >
                <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                <span>{{ t('employees.save') }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.pos-drawer-enter-active .pos-drawer-panel,
.pos-drawer-leave-active .pos-drawer-panel {
  transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1);
}

.pos-drawer-enter-from .pos-drawer-panel,
.pos-drawer-leave-to .pos-drawer-panel {
  transform: translateX(100%);
}

[dir='ltr'] .pos-drawer-enter-from .pos-drawer-panel,
[dir='ltr'] .pos-drawer-leave-to .pos-drawer-panel {
  transform: translateX(-100%);
}
</style>
