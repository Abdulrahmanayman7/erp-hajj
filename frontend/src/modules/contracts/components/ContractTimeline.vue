<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import type { ContractTransition } from '../types/contracts'
import { contractStatusBadgeClass } from '../validation/contractValidation'

const props = defineProps<{
  transitions: ContractTransition[]
}>()

const { t } = useI18n()

const ordered = computed(() =>
  [...props.transitions].sort((a, b) => {
    const aTime = a.created_at ? Date.parse(a.created_at) : 0
    const bTime = b.created_at ? Date.parse(b.created_at) : 0
    return aTime - bTime
  }),
)

function actorLabel(transition: ContractTransition): string {
  return transition.actor?.name ?? t('contracts.systemActor')
}

function formatTimestamp(value: string | null): string {
  if (!value) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value))
  } catch {
    return value
  }
}

function statusLabel(status: string | null): string {
  if (!status) return '—'
  return t(`contracts.status.${status}`)
}
</script>

<template>
  <div class="rounded-2xl border border-brand-border bg-brand-surface p-5">
    <h3 class="text-base font-bold text-brand-text">{{ t('contracts.timelineTitle') }}</h3>
    <p class="mt-1 text-sm text-brand-text-secondary">{{ t('contracts.timelineSubtitle') }}</p>

    <div
      v-if="ordered.length === 0"
      class="mt-6 rounded-xl border border-dashed border-brand-border px-4 py-8 text-center text-sm text-brand-text-muted"
    >
      {{ t('contracts.timelineEmpty') }}
    </div>

    <ol v-else class="relative mt-6 space-y-0 border-s-2 border-brand-border/80 ms-3">
      <li
        v-for="transition in ordered"
        :key="transition.id"
        class="relative pb-6 ps-6 last:pb-0"
      >
        <span
          class="absolute start-[-7px] top-1.5 h-3 w-3 rounded-full bg-brand-primary ring-4 ring-brand-surface"
        />
        <div class="flex flex-wrap items-center gap-2">
          <span
            v-if="transition.from_status"
            class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
            :class="contractStatusBadgeClass(transition.from_status)"
          >
            {{ statusLabel(transition.from_status) }}
          </span>
          <span v-if="transition.from_status" class="text-xs text-brand-text-muted">→</span>
          <span
            class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
            :class="contractStatusBadgeClass(transition.to_status)"
          >
            {{ statusLabel(transition.to_status) }}
          </span>
        </div>
        <p class="mt-2 text-sm font-semibold text-brand-text">{{ actorLabel(transition) }}</p>
        <p class="mt-0.5 text-xs text-brand-text-muted">
          {{ formatTimestamp(transition.created_at) }}
        </p>
        <p
          v-if="transition.comment"
          class="mt-2 rounded-lg bg-brand-bg px-3 py-2 text-sm text-brand-text-secondary"
        >
          {{ transition.comment }}
        </p>
      </li>
    </ol>
  </div>
</template>
