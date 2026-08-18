<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { CheckCircle2, CircleAlert } from 'lucide-vue-next'

import type { AttentionItem } from '../types/dashboard'
import { isSafeAppHref, severityBadgeClass } from '../utils/dashboardDisplay'
import DashboardSectionCard from './DashboardSectionCard.vue'

const props = defineProps<{
  items?: AttentionItem[] | null
}>()

const { t } = useI18n()

const list = computed(() => props.items ?? [])
const isEmpty = computed(() => list.value.length === 0)
</script>

<template>
  <DashboardSectionCard :title="t('dashboard.attention')">
    <div
      v-if="isEmpty"
      class="flex items-start gap-3 rounded-xl bg-emerald-50/70 p-4"
    >
      <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-700" aria-hidden="true" />
      <div>
        <p class="text-sm font-bold text-emerald-900">{{ t('dashboard.attentionClearTitle') }}</p>
        <p class="mt-1 text-xs leading-5 text-emerald-800">{{ t('dashboard.attentionClearDescription') }}</p>
      </div>
    </div>
    <ul
      v-else
      class="divide-y divide-brand-border"
      role="list"
    >
      <li
        v-for="(item, index) in list"
        :key="`${item.type}-${item.entity_id ?? item.count ?? index}`"
      >
        <component
          :is="isSafeAppHref(item.href) ? RouterLink : 'div'"
          :to="isSafeAppHref(item.href) ? item.href : undefined"
          class="flex items-start gap-3 py-3 transition"
          :class="isSafeAppHref(item.href) ? 'hover:bg-brand-primary-soft/40 rounded-xl px-2 -mx-2' : 'px-0'"
        >
          <span
            class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
            :class="severityBadgeClass(item.severity)"
            :aria-label="t(`dashboard.severity.${item.severity}`)"
          >
            <CircleAlert class="h-4 w-4" aria-hidden="true" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-brand-text">
              {{ item.title }}
            </p>
            <p
              v-if="item.subtitle"
              class="mt-0.5 text-xs text-brand-text-secondary"
            >
              {{ item.subtitle }}
            </p>
          </div>
          <span
            v-if="item.count != null"
            class="shrink-0 rounded-lg bg-brand-bg px-2 py-0.5 text-xs font-bold tabular-nums text-brand-text"
          >
            {{ item.count }}
          </span>
        </component>
      </li>
    </ul>
  </DashboardSectionCard>
</template>
