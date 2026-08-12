<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

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
    <p
      v-if="isEmpty"
      class="text-sm text-brand-text-muted"
    >
      {{ t('dashboard.emptyAttention') }}
    </p>
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
            class="mt-0.5 shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold"
            :class="severityBadgeClass(item.severity)"
          >
            {{
              item.severity === 'critical' || item.severity === 'warning' || item.severity === 'info'
                ? t(`dashboard.severity.${item.severity}`)
                : item.severity
            }}
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
            class="shrink-0 rounded-lg bg-[#F4F6F5] px-2 py-0.5 text-xs font-bold tabular-nums text-brand-text"
          >
            {{ item.count }}
          </span>
        </component>
      </li>
    </ul>
  </DashboardSectionCard>
</template>
