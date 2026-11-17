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

function openEdit(position: Position): void {
  editing.value = position
  form.name = position.name
  form.code = position.code ?? ''
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formOpen.value = true
  captureFormDirty()
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
    formOpen.value = false
    resetFormDirty()
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
  <AppFormDrawer
    :open="open"
    :title="t('employees.positionsTitle')"
    :description="t('employees.positionsSubtitle')"
    size="md"
    hide-default-footer
    @update:open="onOuterOpenUpdate"
  >
    <div class="mb-4 flex flex-wrap items-center gap-3 border-b border-brand-border pb-3 -mt-1">
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
  </AppFormDrawer>

  <AppFormDrawer
    :open="formOpen"
    :title="editing ? t('employees.editPositionTitle') : t('employees.createPositionTitle')"
    size="sm"
    :dirty="formDirty"
    :submitting="isSubmitting"
    :cancel-label="t('employees.cancel')"
    :submit-label="t('employees.save')"
    @update:open="onFormOpenUpdate"
    @submit="submitForm"
  >
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
  </AppFormDrawer>
</template>