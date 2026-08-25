<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { Building2, ChevronDown, Layers3, Network } from 'lucide-vue-next'

import type { OrganizationUnit, OrganizationUnitType } from '../types/organization'

defineOptions({ name: 'OrganizationTreeNodes' })

defineProps<{
  nodes: OrganizationUnit[]
  expanded: Set<number>
  selectedId: number | null
  typeLabel: (type: OrganizationUnit['type']) => string
  statusLabel: (status: string) => string
  depth?: number
}>()

defineEmits<{
  toggle: [id: number]
  select: [unit: OrganizationUnit]
}>()

const { t } = useI18n()

/** Cap indent so deep trees stay within ~320px screens without horizontal scroll. */
function indentStyle(depth: number): Record<string, string> {
  const capped = Math.min(depth, 5)
  return { paddingInlineStart: `${capped * 0.65 + 0.25}rem` }
}

function typeIcon(type: OrganizationUnitType) {
  if (type === 'department') return Building2
  if (type === 'section') return Layers3
  return Network
}

function typeBadgeClass(type: OrganizationUnitType): string {
  if (type === 'department') return 'bg-brand-primary-soft text-brand-primary-dark'
  if (type === 'section') return 'bg-brand-gold-soft text-[#8a6a2e]'
  return 'bg-[#F4F6F5] text-brand-text-secondary'
}
</script>

<template>
  <template v-for="node in nodes" :key="node.id">
    <li
      role="treeitem"
      :aria-expanded="(node.children?.length ?? 0) > 0 ? expanded.has(node.id) : undefined"
      :aria-selected="selectedId === node.id"
      class="min-w-0"
    >
      <div
        class="group relative flex min-w-0 cursor-pointer items-start gap-1 rounded-xl py-1.5 pe-2 text-sm transition"
        :class="
          selectedId === node.id
            ? 'bg-brand-primary-soft text-brand-primary-dark shadow-[inset_0_0_0_1.5px_rgba(7,107,82,0.28)]'
            : 'text-brand-text hover:bg-[#F4F6F5]'
        "
        :style="indentStyle(depth ?? 0)"
        @click="$emit('select', node)"
      >
        <span
          v-if="selectedId === node.id"
          class="absolute inset-s-0 top-1.5 bottom-1.5 w-[3px] rounded-full bg-brand-gold"
          aria-hidden="true"
        />

        <button
          v-if="(node.children?.length ?? 0) > 0"
          type="button"
          class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-brand-text-muted transition hover:bg-white hover:text-brand-primary-dark"
          :aria-label="expanded.has(node.id) ? t('organization.collapse') : t('organization.expand')"
          @click.stop="$emit('toggle', node.id)"
        >
          <ChevronDown
            class="h-5 w-5 transition-transform duration-200"
            :class="expanded.has(node.id) ? '' : '-rotate-90'"
            :stroke-width="2"
          />
        </button>
        <span v-else class="inline-block h-11 w-11 shrink-0" aria-hidden="true" />

        <span
          class="mt-2 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
          :class="
            selectedId === node.id
              ? 'bg-white/80 text-brand-primary'
              : 'bg-brand-bg text-brand-text-secondary group-hover:bg-white'
          "
          aria-hidden="true"
        >
          <component :is="typeIcon(node.type)" class="h-3.5 w-3.5" :stroke-width="1.85" />
        </span>

        <div class="min-w-0 flex-1 py-2">
          <p
            class="break-words font-semibold leading-5"
            :class="selectedId === node.id ? 'text-brand-primary-dark' : ''"
          >
            {{ node.name }}
          </p>
          <p
            class="mt-0.5 font-mono text-[11px] tracking-wide text-brand-text-muted"
            dir="ltr"
          >
            {{ node.code }}
          </p>
          <div class="mt-1.5 flex min-w-0 flex-wrap items-center gap-1.5">
            <span
              class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-bold"
              :class="typeBadgeClass(node.type)"
            >
              {{ typeLabel(node.type) }}
            </span>
            <span
              class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
              :class="
                node.status === 'active'
                  ? 'bg-emerald-50 text-emerald-800'
                  : 'bg-slate-100 text-slate-600'
              "
            >
              {{ statusLabel(node.status) }}
            </span>
            <span
              class="rounded-md bg-white/80 px-1.5 py-0.5 text-[10px] font-semibold text-brand-text-muted"
            >
              {{ node.children_count }}
            </span>
            <span
              v-if="node.manager"
              class="max-w-full break-words text-[11px] text-brand-text-secondary"
            >
              {{ node.manager.name }}
            </span>
          </div>
        </div>
      </div>

      <ul
        v-if="(node.children?.length ?? 0) > 0 && expanded.has(node.id)"
        class="mt-0.5 min-w-0"
        role="group"
      >
        <OrganizationTreeNodes
          :nodes="node.children || []"
          :expanded="expanded"
          :selected-id="selectedId"
          :type-label="typeLabel"
          :status-label="statusLabel"
          :depth="(depth ?? 0) + 1"
          @toggle="$emit('toggle', $event)"
          @select="$emit('select', $event)"
        />
      </ul>
    </li>
  </template>
</template>
