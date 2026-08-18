<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { Loader2 } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import {
  useApproveDecisionMutation,
  useCancelDecisionMutation,
  useCloseDecisionMutation,
  useDeleteDecisionMutation,
  useReturnDecisionDraftMutation,
  useSubmitDecisionMutation,
} from '../mutations/useDecisionMutations'
import type { Decision, DecisionLifecycleAction } from '../types/decisions'
import {
  availableLifecycleActions,
  mapDecisionErrorCode,
} from '../validation/decisionValidation'

const props = defineProps<{
  decision: Decision
}>()

const emit = defineEmits<{
  refreshed: []
}>()

const { t } = useI18n()
const { can } = usePermissions()
const { confirm } = useConfirm()
const toast = useToast()
const router = useRouter()

const submit = useSubmitDecisionMutation()
const back = useReturnDecisionDraftMutation()
const approve = useApproveDecisionMutation()
const cancel = useCancelDecisionMutation()
const close = useCloseDecisionMutation()
const remove = useDeleteDecisionMutation()

const comment = ref('')
const commentAction = ref<DecisionLifecycleAction | null>(null)
const actionError = ref('')

const openTasksCount = computed(() => props.decision.tasks_summary?.open ?? 0)
const closeBlocked = computed(
  () => props.decision.status === 'approved' && openTasksCount.value > 0,
)
const actions = computed(() =>
  availableLifecycleActions(props.decision.status).filter((x) => can(x.permission)),
)

const isPending = computed(
  () =>
    submit.isPending.value ||
    back.isPending.value ||
    approve.isPending.value ||
    cancel.isPending.value ||
    close.isPending.value ||
    remove.isPending.value,
)

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('decisions.errors.generic')
  const mapped = mapDecisionErrorCode(error.code)
  return mapped !== 'generic'
    ? t(`decisions.errors.${mapped}`)
    : error.message || t('decisions.errors.generic')
}

function actionButtonClass(action: DecisionLifecycleAction): string {
  if (action === 'submit') {
    return 'border-sky-200 bg-sky-50 text-sky-950 hover:bg-sky-100'
  }
  if (action === 'approve') {
    return 'border-emerald-200 bg-emerald-50 text-emerald-950 hover:bg-emerald-100'
  }
  if (action === 'return-draft') {
    return 'border-amber-200 bg-amber-50 text-amber-950 hover:bg-amber-100'
  }
  if (action === 'close') {
    return 'border-teal-200 bg-teal-50 text-teal-950 hover:bg-teal-100'
  }
  if (action === 'cancel') {
    return 'border-red-200 bg-red-50 text-red-800 hover:bg-red-100'
  }
  return 'border-brand-border bg-brand-surface text-brand-primary-dark hover:bg-brand-primary-soft'
}

async function execute(action: DecisionLifecycleAction, value?: string): Promise<void> {
  const id = props.decision.id
  if (action === 'submit') await submit.mutateAsync({ id })
  else if (action === 'return-draft') await back.mutateAsync({ id, comment: value })
  else if (action === 'approve') await approve.mutateAsync({ id })
  else if (action === 'cancel') await cancel.mutateAsync({ id })
  else await close.mutateAsync({ id })
  toast.success(t(`decisions.toasts.${action.replace('-', '_')}`))
  emit('refreshed')
}

async function run(action: DecisionLifecycleAction): Promise<void> {
  actionError.value = ''
  if (action === 'return-draft') {
    commentAction.value = action
    comment.value = ''
    return
  }
  if (action === 'close' && closeBlocked.value) return
  const ok = await confirm({
    title: t(`decisions.confirm.${action}.title`),
    message: t(`decisions.confirm.${action}.body`),
    confirmLabel: t(`decisions.lifecycle.${action}`),
    cancelLabel: t('decisions.cancel'),
    variant: action === 'cancel' ? 'danger' : 'primary',
  })
  if (!ok) return
  try {
    await execute(action)
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(actionError.value)
  }
}

async function submitComment(): Promise<void> {
  if (!comment.value.trim()) return
  actionError.value = ''
  try {
    await execute('return-draft', comment.value.trim())
    commentAction.value = null
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(actionError.value)
  }
}

async function deleteIt(): Promise<void> {
  actionError.value = ''
  if (
    await confirm({
      title: t('decisions.confirm.delete.title'),
      message: t('decisions.confirm.delete.body'),
      confirmLabel: t('decisions.actions.delete'),
      cancelLabel: t('decisions.cancel'),
      variant: 'danger',
    })
  ) {
    try {
      await remove.mutateAsync(props.decision.id)
      await router.push('/app/decisions')
    } catch (error) {
      actionError.value = apiMessage(error)
      toast.error(actionError.value)
    }
  }
}
</script>

<template>
  <div class="space-y-3">
    <p
      v-if="closeBlocked"
      class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200/70"
    >
      {{ t('decisions.errors.DECISION_CLOSE_NOT_ALLOWED') }}
    </p>

    <div class="flex flex-col gap-2">
      <button
        v-for="item in actions"
        :key="item.action"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border px-4 text-sm font-semibold transition disabled:opacity-50 xl:justify-start"
        :class="actionButtonClass(item.action)"
        :disabled="isPending || (item.action === 'close' && closeBlocked)"
        @click="run(item.action)"
      >
        <Loader2 v-if="isPending" class="h-3.5 w-3.5 animate-spin" />
        {{ t(`decisions.lifecycle.${item.action}`) }}
      </button>

      <button
        v-if="decision.status === 'draft' && can('decisions.delete')"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-red-300 bg-red-50 px-4 text-sm font-semibold text-red-800 transition hover:bg-red-100 disabled:opacity-50 xl:justify-start"
        :disabled="isPending"
        @click="deleteIt"
      >
        {{ t('decisions.actions.delete') }}
      </button>
    </div>

    <p
      v-if="actionError"
      class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-700"
      role="alert"
    >
      {{ actionError }}
    </p>

    <div v-if="commentAction" class="rounded-xl border border-brand-border bg-brand-bg p-3">
      <label class="block text-sm font-semibold text-brand-text">
        {{ t('decisions.fields.comment') }}
      </label>
      <textarea
        v-model="comment"
        rows="3"
        class="mt-2 w-full rounded-xl border border-brand-border bg-brand-surface p-2.5 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
      />
      <div class="mt-2 flex flex-wrap gap-2">
        <button
          type="button"
          class="inline-flex h-10 items-center rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
          :disabled="isPending || !comment.trim()"
          @click="submitComment"
        >
          {{ t('decisions.confirm.return-draft.cta') }}
        </button>
        <button
          type="button"
          class="inline-flex h-10 items-center rounded-xl border border-brand-border px-4 text-sm font-semibold text-brand-text"
          :disabled="isPending"
          @click="commentAction = null"
        >
          {{ t('decisions.cancel') }}
        </button>
      </div>
    </div>
  </div>
</template>
