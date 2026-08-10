<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import type { Decision, DecisionLifecycleAction } from '../types/decisions'
import { availableLifecycleActions } from '../validation/decisionValidation'
import { useApproveDecisionMutation, useCancelDecisionMutation, useCloseDecisionMutation, useDeleteDecisionMutation, useReturnDecisionDraftMutation, useSubmitDecisionMutation } from '../mutations/useDecisionMutations'
const props = defineProps<{ decision: Decision }>(); const emit = defineEmits<{ refreshed: [] }>(); const { t } = useI18n(); const { can } = usePermissions(); const { confirm } = useConfirm(); const toast = useToast(); const router = useRouter()
const submit = useSubmitDecisionMutation(); const back = useReturnDecisionDraftMutation(); const approve = useApproveDecisionMutation(); const cancel = useCancelDecisionMutation(); const close = useCloseDecisionMutation(); const remove = useDeleteDecisionMutation()
const comment = ref(''); const commentAction = ref<DecisionLifecycleAction | null>(null)
const actions = computed(() => availableLifecycleActions(props.decision.status).filter(x => can(x.permission)))
async function execute(action: DecisionLifecycleAction, value?: string) { const id = props.decision.id; if (action === 'submit') await submit.mutateAsync({ id }); else if (action === 'return-draft') await back.mutateAsync({ id, comment: value }); else if (action === 'approve') await approve.mutateAsync({ id }); else if (action === 'cancel') await cancel.mutateAsync({ id }); else await close.mutateAsync({ id }); toast.success(t(`decisions.toasts.${action.replace('-', '_')}`)); emit('refreshed') }
async function run(action: DecisionLifecycleAction) { if (action === 'return-draft') { commentAction.value = action; comment.value = ''; return }; const ok = await confirm({ title: t(`decisions.confirm.${action}.title`), message: t(`decisions.confirm.${action}.body`), confirmLabel: t(`decisions.lifecycle.${action}`), cancelLabel: t('decisions.cancel'), variant: action === 'cancel' ? 'danger' : 'primary' }); if (ok) await execute(action) }
async function submitComment() { if (!comment.value.trim()) return; await execute('return-draft', comment.value.trim()); commentAction.value = null }
async function deleteIt() { if (await confirm({ title: t('decisions.confirm.delete.title'), message: t('decisions.confirm.delete.body'), confirmLabel: t('decisions.actions.delete'), cancelLabel: t('decisions.cancel'), variant: 'danger' })) { await remove.mutateAsync(props.decision.id); await router.push('/app/decisions') } }
</script>
<template><div class="space-y-3"><div class="flex flex-wrap gap-2"><button v-for="item in actions" :key="item.action" class="rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold text-brand-primary-dark" @click="run(item.action)">{{ t(`decisions.lifecycle.${item.action}`) }}</button><button v-if="decision.status === 'draft' && can('decisions.delete')" class="rounded-xl border border-red-200 px-4 py-2 text-sm text-red-700" @click="deleteIt">{{ t('decisions.actions.delete') }}</button></div><div v-if="commentAction" class="rounded-xl bg-brand-bg p-3"><label class="block text-sm font-semibold">{{ t('decisions.fields.comment') }}</label><textarea v-model="comment" rows="3" class="mt-2 w-full rounded-xl border border-brand-border p-2" /><div class="mt-2 flex gap-2"><button class="rounded-lg bg-brand-primary-dark px-3 py-2 text-white" @click="submitComment">{{ t('decisions.confirm.return-draft.cta') }}</button><button class="rounded-lg border px-3 py-2" @click="commentAction = null">{{ t('decisions.cancel') }}</button></div></div></div></template>
