<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  Briefcase,
  Building2,
  ChevronDown,
  IdCard,
  Layers3,
  Network,
  UserRound,
  Users,
} from 'lucide-vue-next'

import type { OrganizationTreeUnit } from '../api/organizationTreeApi'
import type { OrganizationUnitType } from '../types/organization'

defineOptions({ name: 'OrganizationTreeProjectionNode' })

const props = defineProps<{
  unit: OrganizationTreeUnit
  depth: number
  expanded: Set<number>
}>()

const emit = defineEmits<{
  toggle: [id: number]
}>()

const { t } = useI18n()

const isOpen = computed(() => props.expanded.has(props.unit.id))
const hasChildren = computed(() => props.unit.children.length > 0)
const hasDetails = computed(
  () =>
    hasChildren.value ||
    props.unit.employees.length > 0 ||
    props.unit.positions.length > 0,
)

function typeIcon(type: string) {
  if (type === 'department') return Building2
  if (type === 'section') return Layers3
  return Network
}

function typeBadgeClass(type: string): string {
  if (type === 'department') return 'bg-brand-primary-soft text-brand-primary-dark'
  if (type === 'section') return 'bg-brand-gold-soft text-[#8a6a2e]'
  return 'bg-[#EEF2F0] text-brand-text-secondary'
}

function typeLabel(type: string): string {
  if (type === 'department' || type === 'section' || type === 'unit') {
    return t(`organization.types.${type as OrganizationUnitType}`)
  }
  return type
}

function statusLabel(status: string): string {
  if (status === 'active' || status === 'inactive') {
    return t(`organization.status.${status}`)
  }
  return status
}

function statusClass(status: string): string {
  return status === 'active'
    ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/80'
    : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200/80'
}

function indentStyle(depth: number): Record<string, string> {
  const capped = Math.min(depth, 6)
  return { paddingInlineStart: `${capped * 1.1 + 0.35}rem` }
}

function positionName(positionId: number | null): string | null {
  if (positionId == null) return null
  return props.unit.positions.find((position) => position.id === positionId)?.name ?? null
}
</script>

<template>
  <li
    role="treeitem"
    class="min-w-0"
    :aria-expanded="hasDetails ? isOpen : undefined"
  >
    <div
      class="group relative flex min-w-0 items-stretch gap-1 rounded-2xl transition"
      :class="isOpen && hasDetails ? 'bg-brand-bg/55' : 'hover:bg-brand-bg/70'"
      :style="indentStyle(depth)"
    >
      <span
        v-if="depth > 0"
        class="pointer-events-none absolute inset-s-3 top-0 bottom-0 w-px bg-gradient-to-b from-brand-border via-brand-border/70 to-transparent"
        aria-hidden="true"
      />

      <button
        v-if="hasDetails"
        type="button"
        class="ms-1 inline-flex h-12 w-10 shrink-0 items-center justify-center self-center rounded-xl text-brand-text-muted transition hover:bg-white hover:text-brand-primary"
        :aria-label="isOpen ? t('organization.collapse') : t('organization.expand')"
        @click="emit('toggle', unit.id)"
      >
        <ChevronDown
          class="h-4.5 w-4.5 transition-transform duration-200"
          :class="isOpen ? '' : '-rotate-90'"
          :stroke-width="2.1"
        />
      </button>
      <span v-else class="ms-1 inline-block h-12 w-10 shrink-0" aria-hidden="true" />

      <div class="flex min-w-0 flex-1 items-start gap-3 px-2 py-2.5 pe-3">
        <span
          class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl shadow-sm ring-1 ring-black/5"
          :class="typeBadgeClass(unit.type)"
          aria-hidden="true"
        >
          <component :is="typeIcon(unit.type)" class="h-4 w-4" :stroke-width="1.9" />
        </span>

        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <p class="truncate text-[15px] font-bold leading-snug text-brand-text">
              {{ unit.name }}
            </p>
            <span
              class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
              :class="typeBadgeClass(unit.type)"
            >
              {{ typeLabel(unit.type) }}
            </span>
            <span
              class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
              :class="statusClass(unit.status)"
            >
              {{ statusLabel(unit.status) }}
            </span>
          </div>

          <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-brand-text-muted">
            <span v-if="unit.code" class="font-mono" dir="ltr">{{ unit.code }}</span>
            <span v-if="unit.children.length" class="inline-flex items-center gap-1">
              <Layers3 class="h-3 w-3" />
              {{ t('organizationTree.childUnits', { count: unit.children.length }) }}
            </span>
            <span v-if="unit.positions.length" class="inline-flex items-center gap-1">
              <Briefcase class="h-3 w-3" />
              {{ t('organizationTree.positionCount', { count: unit.positions.length }) }}
            </span>
          </div>
        </div>

        <span
          class="mt-0.5 inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-brand-text-secondary shadow-sm ring-1 ring-brand-border/80"
          :title="t('organizationTree.employeeCount', { count: unit.employees.length })"
        >
          <Users class="h-3.5 w-3.5 text-brand-primary" />
          {{ unit.employees.length }}
        </span>
      </div>
    </div>

    <div
      v-if="isOpen && hasDetails"
      class="relative pb-2 pt-1"
      :style="{ paddingInlineStart: `${Math.min(depth, 6) * 1.1 + 2.75}rem` }"
    >
      <div
        v-if="unit.positions.length || unit.employees.length"
        class="mb-2 grid gap-2 sm:grid-cols-2"
      >
        <section
          v-if="unit.positions.length"
          class="rounded-2xl border border-brand-border/80 bg-white/80 p-3 shadow-sm"
        >
          <header class="mb-2 flex items-center gap-2 text-xs font-bold text-brand-text">
            <Briefcase class="h-3.5 w-3.5 text-brand-primary" />
            {{ t('organizationTree.positions') }}
            <span class="rounded-full bg-brand-bg px-1.5 py-0.5 text-[10px] font-semibold text-brand-text-secondary">
              {{ unit.positions.length }}
            </span>
          </header>
          <ul class="space-y-1.5">
            <li
              v-for="position in unit.positions"
              :key="`p-${position.id}`"
              class="flex items-center justify-between gap-2 rounded-xl bg-brand-bg/80 px-2.5 py-1.5 text-xs"
            >
              <span class="min-w-0 truncate font-medium text-brand-text">{{ position.name }}</span>
              <span
                class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                :class="
                  position.is_active
                    ? 'bg-emerald-50 text-emerald-700'
                    : 'bg-slate-100 text-slate-500'
                "
              >
                {{
                  position.is_active
                    ? t('organization.status.active')
                    : t('organization.status.inactive')
                }}
              </span>
            </li>
          </ul>
        </section>

        <section
          v-if="unit.employees.length"
          class="rounded-2xl border border-brand-border/80 bg-white/80 p-3 shadow-sm sm:col-span-1"
          :class="unit.positions.length ? '' : 'sm:col-span-2'"
        >
          <header class="mb-2 flex items-center gap-2 text-xs font-bold text-brand-text">
            <UserRound class="h-3.5 w-3.5 text-brand-primary" />
            {{ t('organizationTree.employees') }}
            <span class="rounded-full bg-brand-bg px-1.5 py-0.5 text-[10px] font-semibold text-brand-text-secondary">
              {{ unit.employees.length }}
            </span>
          </header>
          <ul class="space-y-1.5">
            <li
              v-for="employee in unit.employees"
              :key="`e-${employee.id}`"
              class="rounded-xl border border-brand-border/60 bg-brand-surface px-2.5 py-2"
            >
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <p class="truncate text-xs font-semibold text-brand-text">{{ employee.name }}</p>
                  <p
                    v-if="employee.employee_number"
                    class="mt-0.5 font-mono text-[10px] text-brand-text-muted"
                    dir="ltr"
                  >
                    {{ employee.employee_number }}
                  </p>
                </div>
                <span
                  class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                  :class="statusClass(employee.status)"
                >
                  {{ statusLabel(employee.status) }}
                </span>
              </div>
              <div class="mt-1.5 flex flex-wrap gap-1.5 text-[10px] text-brand-text-secondary">
                <span
                  class="inline-flex items-center gap-1 rounded-lg bg-brand-bg px-1.5 py-0.5"
                >
                  <IdCard class="h-3 w-3" />
                  {{
                    positionName(employee.position_id) ??
                    t('organizationTree.noPosition')
                  }}
                </span>
                <span
                  v-if="employee.supervisor_employee_id"
                  class="inline-flex items-center rounded-lg bg-brand-bg px-1.5 py-0.5"
                >
                  {{ t('organizationTree.supervisorRef', { id: employee.supervisor_employee_id }) }}
                </span>
              </div>
            </li>
          </ul>
        </section>
      </div>

      <ul v-if="unit.children.length" class="space-y-1.5" role="group">
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
