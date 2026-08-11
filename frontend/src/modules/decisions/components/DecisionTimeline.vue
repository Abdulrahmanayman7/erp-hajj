<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DecisionTransition } from '../types/decisions'
import { decisionStatusBadgeClass } from '../validation/decisionValidation'
const props = defineProps<{ transitions: DecisionTransition[] }>(); const { t } = useI18n()
const ordered = computed(() => [...props.transitions].sort((a, b) => Date.parse(a.created_at ?? '') - Date.parse(b.created_at ?? '')))
</script>
<template><section class="rounded-2xl border border-brand-border bg-brand-surface p-5"><h3 class="font-bold">{{ t('decisions.timelineTitle') }}</h3><p class="mt-1 text-sm text-brand-text-secondary">{{ t('decisions.timelineSubtitle') }}</p><p v-if="!ordered.length" class="mt-4 text-sm text-brand-text-muted">{{ t('decisions.timelineEmpty') }}</p><ol v-else class="mt-5 space-y-4 border-s-2 border-brand-border ps-5"><li v-for="item in ordered" :key="item.id"><span class="rounded-full px-2 py-1 text-xs" :class="decisionStatusBadgeClass(item.to_status)">{{ t(`decisions.status.${item.to_status}`) }}</span><p class="mt-2 text-sm">{{ item.performed_by?.name ?? t('decisions.systemActor') }}</p><p v-if="item.comment" class="mt-1 text-sm text-brand-text-secondary">{{ item.comment }}</p></li></ol></section></template>
