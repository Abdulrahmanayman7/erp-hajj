<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, MessageSquareText, UserRound } from 'lucide-vue-next'

import type { DecisionTransition } from '../types/decisions'
import { decisionStatusBadgeClass } from '../validation/decisionValidation'

const props = defineProps<{
  transitions: DecisionTransition[]
}>()

const { t } = useI18n()

const ordered = computed(() =>
  [...props.transitions].sort((a, b) => {
    const aTime = a.created_at ? Date.parse(a.created_at) : 0
    const bTime = b.created_at ? Date.parse(b.created_at) : 0
    return aTime - bTime
  }),
)

function actorLabel(transition: DecisionTransition): string {
  return transition.performed_by?.name ?? t('decisions.systemActor')
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
  return t(`decisions.status.${status}`)
}
</script>

<template>
  <section class="rounded-2xl border border-brand-border bg-brand-surface">
    <header class="border-b border-brand-border px-5 py-4">
      <h3 class="text-base font-bold text-brand-text">{{ t('decisions.timelineTitle') }}</h3>
      <p class="mt-1 text-sm text-brand-text-secondary">{{ t('decisions.timelineSubtitle') }}</p>
    </header>

    <div
      v-if="ordered.length === 0"
      class="px-5 py-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('decisions.timelineEmpty') }}
    </div>

    <ol v-else class="relative space-y-0 px-5 py-5">
      <li
        v-for="(transition, index) in ordered"
        :key="transition.id"
        class="relative flex gap-4 pb-6 last:pb-0"
      >
        <div class="relative flex w-4 shrink-0 flex-col items-center">
          <span
            class="mt-1.5 z-10 h-3 w-3 rounded-full bg-brand-primary ring-[3px] ring-brand-primary/15"
          />
          <span
            v-if="index < ordered.length - 1"
            class="absolute top-5 bottom-0 w-px bg-brand-border"
            aria-hidden="true"
          />
        </div>

        <div class="min-w-0 flex-1 rounded-xl border border-brand-border/80 bg-brand-bg/40 px-4 py-3">
          <div class="flex flex-wrap items-center gap-2">
            <span
              v-if="transition.from_status"
              class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
              :class="decisionStatusBadgeClass(transition.from_status)"
            >
              {{ statusLabel(transition.from_status) }}
            </span>
            <ArrowLeft
              v-if="transition.from_status"
              class="h-3.5 w-3.5 shrink-0 text-brand-text-muted"
              :stroke-width="2"
              aria-hidden="true"
            />
            <span
              class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
              :class="decisionStatusBadgeClass(transition.to_status)"
            >
              {{ statusLabel(transition.to_status) }}
            </span>
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-brand-text-muted">
            <span class="inline-flex items-center gap-1.5 font-semibold text-brand-text">
              <UserRound class="h-3.5 w-3.5 text-brand-text-secondary" :stroke-width="1.75" />
              {{ actorLabel(transition) }}
            </span>
            <span>{{ formatTimestamp(transition.created_at) }}</span>
          </div>

          <p
            v-if="transition.comment"
            class="mt-3 flex gap-2 rounded-lg border border-brand-border/70 bg-brand-surface px-3 py-2 text-sm text-brand-text-secondary"
          >
            <MessageSquareText
              class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-text-muted"
              :stroke-width="1.75"
            />
            <span>{{ transition.comment }}</span>
          </p>
        </div>
      </li>
    </ol>
  </section>
</template>
