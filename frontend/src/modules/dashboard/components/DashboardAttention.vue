<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { CheckCircle2, CircleAlert } from 'lucide-vue-next'

import type { AttentionItem } from '../types/dashboard'
import { isSafeAppHref, severityBadgeClass } from '../utils/dashboardDisplay'

const props = defineProps<{
  items?: AttentionItem[] | null
}>()

const { t } = useI18n()

const list = computed(() => props.items ?? [])
const isEmpty = computed(() => list.value.length === 0)
</script>

<template>
  <!-- Clear state: single elegant status surface (not nested cards) -->
  <div
    v-if="isEmpty"
    class="app-status-success"
  >
    <span class="app-status-success__icon" aria-hidden="true">
      <CheckCircle2 class="h-5 w-5" :stroke-width="1.9" />
    </span>
    <div class="min-w-0 pt-0.5">
      <p class="text-sm font-semibold text-brand-text">{{ t('dashboard.attentionClearTitle') }}</p>
      <p class="mt-0.5 text-[13px] leading-relaxed text-brand-text-secondary">
        {{ t('dashboard.attentionClearDescription') }}
      </p>
    </div>
  </div>

  <section
    v-else
    class="app-surface-flat p-4 md:p-5"
  >
    <h2 class="app-type-section mb-3">{{ t('dashboard.attention') }}</h2>
    <ul class="divide-y divide-[var(--border-soft)]" role="list">
      <li
        v-for="(item, index) in list"
        :key="`${item.type}-${item.entity_id ?? item.count ?? index}`"
      >
        <component
          :is="isSafeAppHref(item.href) ? RouterLink : 'div'"
          :to="isSafeAppHref(item.href) ? item.href : undefined"
          class="flex items-start gap-3 py-3 transition first:pt-0 last:pb-0"
          :class="isSafeAppHref(item.href) ? 'hover:opacity-90' : ''"
        >
          <span
            class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px]"
            :class="severityBadgeClass(item.severity)"
            :aria-label="t(`dashboard.severity.${item.severity}`)"
          >
            <CircleAlert class="h-4 w-4" aria-hidden="true" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-brand-text">{{ item.title }}</p>
            <p
              v-if="item.subtitle"
              class="mt-0.5 text-[13px] text-brand-text-secondary"
            >
              {{ item.subtitle }}
            </p>
          </div>
          <span
            v-if="item.count != null"
            class="shrink-0 rounded-[8px] bg-[var(--surface-muted)] px-2 py-0.5 text-xs font-semibold tabular-nums text-brand-text"
          >
            {{ item.count }}
          </span>
        </component>
      </li>
    </ul>
  </section>
</template>
