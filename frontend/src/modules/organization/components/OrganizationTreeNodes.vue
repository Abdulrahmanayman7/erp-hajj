<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next'

import type { OrganizationUnit } from '../types/organization'

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
</script>

<template>
  <template v-for="node in nodes" :key="node.id">
    <li
      role="treeitem"
      :aria-expanded="(node.children?.length ?? 0) > 0 ? expanded.has(node.id) : undefined"
    >
      <div
        class="flex cursor-pointer items-center gap-1 rounded-xl px-2 py-2 text-sm hover:bg-brand-canvas"
        :class="selectedId === node.id ? 'bg-brand-primary/10 text-brand-primary' : 'text-brand-ink'"
        :style="{ paddingInlineStart: `${(depth ?? 0) * 1.1 + 0.5}rem` }"
        @click="$emit('select', node)"
      >
        <button
          v-if="(node.children?.length ?? 0) > 0"
          type="button"
          class="rounded p-0.5 hover:bg-white/80"
          @click.stop="$emit('toggle', node.id)"
        >
          <ChevronDown
            class="h-4 w-4 transition"
            :class="expanded.has(node.id) ? '' : '-rotate-90'"
          />
        </button>
        <span v-else class="inline-block w-5" />
        <span class="min-w-0 flex-1 truncate font-medium">{{ node.name }}</span>
        <span class="hidden text-xs text-brand-muted sm:inline">{{ typeLabel(node.type) }}</span>
        <span
          class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
          :class="
            node.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
          "
        >
          {{ statusLabel(node.status) }}
        </span>
        <span class="text-xs text-brand-muted">({{ node.children_count }})</span>
      </div>
      <ul v-if="(node.children?.length ?? 0) > 0 && expanded.has(node.id)" role="group">
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
