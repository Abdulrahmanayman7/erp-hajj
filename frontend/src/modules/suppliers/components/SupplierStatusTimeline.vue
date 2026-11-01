<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { UserRound } from 'lucide-vue-next'

import type { SupplierTransition } from '../types/suppliers'
import { supplierStatusBadgeClass } from '../validation/supplierValidation'

const props = defineProps<{
  transitions: SupplierTransition[]
}>()

const { t } = useI18n()

const ordered = computed(() =>
  [...props.transitions].sort((a, b) => {
    const aTime = a.created_at ? Date.parse(a.created_at) : 0
    const bTime = b.created_at ? Date.parse(b.created_at) : 0
    return aTime - bTime
  }),
)

function actorLabel(transition: SupplierTransition): string {
  return transition.actor?.name ?? t('suppliers.systemActor')
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
  return t(`suppliers.status.${status}`)
}
</script>

<template>
  <section class="rounded-2xl border border-brand-border bg-brand-surface">
    <header class="border-b border-brand-border px-5 py-4">
      <h3 class="text-base font-bold text-brand-text">{{ t('suppliers.timelineTitle') }}</h3>
      <p class="mt-1 text-sm text-brand-text-secondary">{{ t('suppliers.timelineSubtitle') }}</p>
    </header>

    <div
      v-if="ordered.length === 0"
      class="px-5 py-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('suppliers.timelineEmpty') }}
    </div>

    <ol v-else class="relative space-y-0 px-5 py-5">
      <li
        v-for="(transition, index) in ordered"
        :key="transition.id"
        class="relative flex gap-4 pb-6 last:pb-0"
      >
        <div class="relative flex w-4 shrink-0 flex-col items-center">
          <span
            class="z-10 mt-1.5 h-3 w-3 rounded-full bg-brand-primary ring-[3px] ring-brand-primary/15"
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
              :class="supplierStatusBadgeClass(transition.from_status as never)"
            >
              {{ statusLabel(transition.from_status) }}
            </span>
            <span class="text-xs text-brand-text-muted">←</span>
            <span
              class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
              :class="supplierStatusBadgeClass(transition.to_status as never)"
            >
              {{ statusLabel(transition.to_status) }}
            </span>
          </div>
          <p v-if="transition.reason" class="mt-2 text-sm text-brand-text-secondary">
            {{ transition.reason }}
          </p>
          <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-brand-text-muted">
            <UserRound class="h-3.5 w-3.5" aria-hidden="true" />
            <span>{{ actorLabel(transition) }}</span>
            <span>·</span>
            <time :datetime="transition.created_at ?? undefined">{{
              formatTimestamp(transition.created_at)
            }}</time>
          </div>
        </div>
      </li>
    </ol>
  </section>
</template>
