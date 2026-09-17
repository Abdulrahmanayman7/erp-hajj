<script setup lang="ts">
import { ChevronDown, ChevronLeft, Users } from 'lucide-vue-next'

import type { OrganizationTreeUnit } from '../api/organizationTreeApi'

defineOptions({ name: 'OrganizationTreeProjectionNode' })

defineProps<{
  unit: OrganizationTreeUnit
  depth: number
  expanded: Set<number>
}>()

const emit = defineEmits<{
  toggle: [id: number]
}>()

function hasDetails(unit: OrganizationTreeUnit): boolean {
  return unit.children.length > 0 || unit.employees.length > 0 || unit.positions.length > 0
}
</script>

<template>
  <li role="treeitem" :aria-expanded="hasDetails(unit) ? expanded.has(unit.id) : undefined">
    <button
      type="button"
      class="flex w-full items-start gap-2 rounded-xl px-2 py-2 text-start transition hover:bg-brand-bg"
      :style="{ paddingInlineStart: `${depth * 16 + 8}px` }"
      @click="emit('toggle', unit.id)"
    >
      <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center text-brand-text-muted">
        <ChevronDown v-if="hasDetails(unit) && expanded.has(unit.id)" class="h-4 w-4" />
        <ChevronLeft v-else-if="hasDetails(unit)" class="h-4 w-4" />
      </span>
      <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-semibold text-brand-text">{{ unit.name }}</span>
        <span class="mt-0.5 block text-xs text-brand-text-muted">
          {{ unit.type }} · {{ unit.status }}
          <span v-if="unit.code" dir="ltr"> · {{ unit.code }}</span>
        </span>
      </span>
      <span
        class="inline-flex items-center gap-1 rounded-lg bg-brand-bg px-2 py-0.5 text-[11px] text-brand-text-secondary"
      >
        <Users class="h-3 w-3" />
        {{ unit.employees.length }}
      </span>
    </button>

    <div
      v-if="expanded.has(unit.id)"
      class="pb-2"
      :style="{ paddingInlineStart: `${depth * 16 + 28}px` }"
    >
      <div v-if="unit.positions.length" class="mb-2 space-y-1">
        <p class="text-[11px] font-medium text-brand-text-muted">مناصب</p>
        <ul class="space-y-0.5">
          <li
            v-for="position in unit.positions"
            :key="`p-${position.id}`"
            class="rounded-lg bg-brand-bg/60 px-2 py-1 text-xs text-brand-text-secondary"
          >
            {{ position.name }}
          </li>
        </ul>
      </div>
      <div v-if="unit.employees.length" class="mb-2 space-y-1">
        <p class="text-[11px] font-medium text-brand-text-muted">موظفون</p>
        <ul class="space-y-0.5">
          <li
            v-for="employee in unit.employees"
            :key="`e-${employee.id}`"
            class="rounded-lg border border-brand-border/70 px-2 py-1.5 text-xs"
          >
            <span class="font-medium text-brand-text">{{ employee.name }}</span>
            <span v-if="!employee.position_id" class="ms-2 text-brand-text-muted">بدون منصب</span>
            <span v-if="employee.supervisor_employee_id" class="ms-2 text-brand-text-muted">
              مشرف #{{ employee.supervisor_employee_id }}
            </span>
          </li>
        </ul>
      </div>
      <ul v-if="unit.children.length" class="space-y-1" role="group">
        <OrganizationTreeProjectionNode
          v-for="child in unit.children"
          :key="child.id"
          :unit="child"
          :depth="depth + 1"
          :expanded="expanded"
          @toggle="emit('toggle', $event)"
        />
      </ul>
    </div>
  </li>
</template>
