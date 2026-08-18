<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import { employeeSelectOption, toSelectId } from '@/shared/lookups/selectOptions'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import {
  useAssignTaskMutation,
  useCancelTaskMutation,
  useCompleteTaskMutation,
  useDeleteTaskMutation,
  useStartTaskMutation,
  useUpdateTaskProgressMutation,
} from '../mutations/useTaskMutations'
import type { Task } from '../types/tasks'
import { canSelfServiceActions } from '../validation/taskValidation'

const props = defineProps<{ task: Task; linkedEmployeeId: number | null }>()
const emit = defineEmits<{ refreshed: [] }>()
const { t } = useI18n()
const { can } = usePermissions()
const { confirm } = useConfirm()
const toast = useToast()
const router = useRouter()

const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })
const selectedAssignee = computed(() =>
  props.task.assigned_to_employee
    ? employeeSelectOption(props.task.assigned_to_employee)
    : null,
)

const assign = useAssignTaskMutation()
const start = useStartTaskMutation()
const progress = useUpdateTaskProgressMutation()
const complete = useCompleteTaskMutation()
const cancel = useCancelTaskMutation()
const remove = useDeleteTaskMutation()

type DialogKind = 'assign' | 'progress' | 'complete' | 'cancel' | null
const activeDialog = ref<DialogKind>(null)
const assignEmployeeId = ref<number | ''>('')
const assignComment = ref('')
const progressValue = ref(0)
const completionNotes = ref('')
const cancelComment = ref('')

const isTerminal = computed(() => props.task.status === 'completed' || props.task.status === 'cancelled')
const selfService = computed(() => canSelfServiceActions(props.task, props.linkedEmployeeId, can))
const canAssign = computed(() => can('tasks.assign') && !isTerminal.value)
const canCancel = computed(() => can('tasks.change_status') && ['draft', 'assigned', 'in_progress'].includes(props.task.status))
const canDelete = computed(() => can('tasks.delete') && props.task.status === 'draft')

function closeDialog(): void {
  activeDialog.value = null
}

function openAssign(): void {
  assignEmployeeId.value = props.task.assigned_to_employee_id ?? ''
  assignComment.value = ''
  activeDialog.value = 'assign'
}
function openProgress(): void {
  progressValue.value = props.task.progress_percent
  activeDialog.value = 'progress'
}
function openComplete(): void {
  completionNotes.value = ''
  activeDialog.value = 'complete'
}
function openCancel(): void {
  cancelComment.value = ''
  activeDialog.value = 'cancel'
}

async function submitAssign(): Promise<void> {
  if (assignEmployeeId.value === '') return
  await assign.mutateAsync({ id: props.task.id, assignedToEmployeeId: Number(assignEmployeeId.value), comment: assignComment.value.trim() || undefined })
  toast.success(t('tasks.toasts.assigned'))
  closeDialog()
  emit('refreshed')
}

async function runStart(): Promise<void> {
  const ok = await confirm({ title: t('tasks.confirm.start.title'), message: t('tasks.confirm.start.body'), confirmLabel: t('tasks.lifecycle.start'), cancelLabel: t('tasks.cancel'), variant: 'primary' })
  if (!ok) return
  await start.mutateAsync({ id: props.task.id })
  toast.success(t('tasks.toasts.started'))
  emit('refreshed')
}

async function submitProgress(): Promise<void> {
  await progress.mutateAsync({ id: props.task.id, progressPercent: progressValue.value })
  toast.success(t('tasks.toasts.progressUpdated'))
  closeDialog()
  emit('refreshed')
}

async function submitComplete(): Promise<void> {
  if (!completionNotes.value.trim()) return
  await complete.mutateAsync({ id: props.task.id, completionNotes: completionNotes.value.trim() })
  toast.success(t('tasks.toasts.completed'))
  closeDialog()
  emit('refreshed')
}

async function submitCancel(): Promise<void> {
  if (!cancelComment.value.trim()) return
  await cancel.mutateAsync({ id: props.task.id, comment: cancelComment.value.trim() })
  toast.success(t('tasks.toasts.cancelled'))
  closeDialog()
  emit('refreshed')
}

async function deleteIt(): Promise<void> {
  const ok = await confirm({ title: t('tasks.confirm.delete.title'), message: t('tasks.confirm.delete.body'), confirmLabel: t('tasks.actions.delete'), cancelLabel: t('tasks.cancel'), variant: 'danger' })
  if (!ok) return
  await remove.mutateAsync({ id: props.task.id, decisionId: props.task.decision_id })
  await router.push('/app/tasks')
}
</script>
<template>
  <div class="space-y-3">
    <div class="flex flex-col gap-2">
      <button
        v-if="canAssign"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-sky-200 bg-sky-50 px-4 text-sm font-semibold text-sky-950 transition hover:bg-sky-100 xl:justify-start"
        @click="openAssign"
      >
        {{ t(task.assigned_to_employee_id ? 'tasks.lifecycle.reassign' : 'tasks.lifecycle.assign') }}
      </button>
      <button
        v-if="selfService.canStart"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-brand-primary/30 bg-brand-primary-soft px-4 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-primary/15 xl:justify-start"
        @click="runStart"
      >
        {{ t('tasks.lifecycle.start') }}
      </button>
      <button
        v-if="selfService.canProgress"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-4 text-sm font-semibold text-amber-950 transition hover:bg-amber-100 xl:justify-start"
        @click="openProgress"
      >
        {{ t('tasks.lifecycle.progress') }}
      </button>
      <button
        v-if="selfService.canComplete"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-950 transition hover:bg-emerald-100 xl:justify-start"
        @click="openComplete"
      >
        {{ t('tasks.lifecycle.complete') }}
      </button>
      <button
        v-if="canCancel"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-red-200 bg-red-50 px-4 text-sm font-semibold text-red-800 transition hover:bg-red-100 xl:justify-start"
        @click="openCancel"
      >
        {{ t('tasks.lifecycle.cancel') }}
      </button>
      <button
        v-if="canDelete"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-red-300 bg-red-50 px-4 text-sm font-semibold text-red-800 transition hover:bg-red-100 xl:justify-start"
        @click="deleteIt"
      >
        {{ t('tasks.actions.delete') }}
      </button>
    </div>

    <div v-if="activeDialog === 'assign'" class="rounded-xl bg-brand-bg p-3">
      <label class="block text-sm font-semibold">{{ t('tasks.fields.assignee') }}</label>
      <AppRemoteSelect
        class="mt-2"
        :model-value="assignEmployeeId"
        query-key="employees-active"
        :fetcher="fetchActiveEmployees"
        :map-option="employeeSelectOption"
        :selected-option="selectedAssignee"
        :enabled="activeDialog === 'assign'"
        @update:model-value="assignEmployeeId = toSelectId($event)"
      />
      <label class="mt-3 block text-sm font-semibold">{{ t('tasks.fields.comment') }}</label>
      <textarea v-model="assignComment" rows="2" class="mt-2 w-full rounded-xl border border-brand-border p-2" />
      <div class="mt-3 flex gap-2">
        <button class="rounded-lg bg-brand-primary-dark px-3 py-2 text-white disabled:opacity-60" :disabled="assignEmployeeId === '' || assign.isPending.value" @click="submitAssign">{{ t('tasks.lifecycle.confirmAssign') }}</button>
        <button class="rounded-lg border px-3 py-2" @click="closeDialog">{{ t('tasks.cancel') }}</button>
      </div>
    </div>

    <div v-if="activeDialog === 'progress'" class="rounded-xl bg-brand-bg p-3">
      <label class="block text-sm font-semibold">{{ t('tasks.fields.progressPercent') }}</label>
      <div class="mt-2 flex items-center gap-3">
        <input v-model.number="progressValue" type="range" min="0" max="100" class="flex-1" />
        <input v-model.number="progressValue" type="number" min="0" max="100" class="h-10 w-20 rounded-lg border border-brand-border px-2 text-center" />
        <span class="text-sm font-semibold">%</span>
      </div>
      <div class="mt-3 flex gap-2">
        <button class="rounded-lg bg-brand-primary-dark px-3 py-2 text-white disabled:opacity-60" :disabled="progress.isPending.value" @click="submitProgress">{{ t('tasks.lifecycle.confirmProgress') }}</button>
        <button class="rounded-lg border px-3 py-2" @click="closeDialog">{{ t('tasks.cancel') }}</button>
      </div>
    </div>

    <div v-if="activeDialog === 'complete'" class="rounded-xl bg-brand-bg p-3">
      <label class="block text-sm font-semibold">{{ t('tasks.fields.completionNotes') }}</label>
      <textarea v-model="completionNotes" rows="3" class="mt-2 w-full rounded-xl border border-brand-border p-2" :placeholder="t('tasks.completionNotesHint')" />
      <div class="mt-3 flex gap-2">
        <button class="rounded-lg bg-brand-primary-dark px-3 py-2 text-white disabled:opacity-60" :disabled="!completionNotes.trim() || complete.isPending.value" @click="submitComplete">{{ t('tasks.lifecycle.confirmComplete') }}</button>
        <button class="rounded-lg border px-3 py-2" @click="closeDialog">{{ t('tasks.cancel') }}</button>
      </div>
    </div>

    <div v-if="activeDialog === 'cancel'" class="rounded-xl bg-brand-bg p-3">
      <label class="block text-sm font-semibold">{{ t('tasks.fields.comment') }}</label>
      <textarea v-model="cancelComment" rows="3" class="mt-2 w-full rounded-xl border border-brand-border p-2" />
      <div class="mt-3 flex gap-2">
        <button class="rounded-lg bg-brand-primary-dark px-3 py-2 text-white disabled:opacity-60" :disabled="!cancelComment.trim() || cancel.isPending.value" @click="submitCancel">{{ t('tasks.lifecycle.confirmCancel') }}</button>
        <button class="rounded-lg border px-3 py-2" @click="closeDialog">{{ t('tasks.cancel') }}</button>
      </div>
    </div>
  </div>
</template>
