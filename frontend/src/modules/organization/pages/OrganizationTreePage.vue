<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  Building2,
  FolderTree,
  LoaderCircle,
  Network,
  RefreshCw,
  UserRound,
  Users,
} from 'lucide-vue-next'

import AppPageHeader from '@/shared/components/AppPageHeader.vue'

import OrganizationTreeProjectionNode from '../components/OrganizationTreeProjectionNode.vue'
import type { OrganizationTreeUnit } from '../api/organizationTreeApi'
import { useOrganizationTreeQuery } from '../queries/useOrganizationTreeQuery'

const { t } = useI18n()
const { data, isLoading, isError, refetch, isFetching } = useOrganizationTreeQuery()

const expanded = ref<Set<number>>(new Set())
const didAutoExpand = ref(false)

const tree = computed(() => data.value)
const units = computed(() => tree.value?.organization.units ?? [])

const stats = computed(() => {
  let unitCount = 0
  let employeeCount = 0
  let positionCount = 0

  const walk = (nodes: OrganizationTreeUnit[]): void => {
    for (const node of nodes) {
      unitCount += 1
      employeeCount += node.employees.length
      positionCount += node.positions.length
      walk(node.children)
    }
  }

  walk(units.value)
  return { unitCount, employeeCount, positionCount }
})

function toggle(id: number): void {
  const next = new Set(expanded.value)
  if (next.has(id)) {
    next.delete(id)
  } else {
    next.add(id)
  }
  expanded.value = next
}

function expandAll(list: OrganizationTreeUnit[]): void {
  const next = new Set<number>()
  const walk = (nodes: OrganizationTreeUnit[]): void => {
    for (const node of nodes) {
      next.add(node.id)
      walk(node.children)
    }
  }
  walk(list)
  expanded.value = next
}

function collapseAll(): void {
  expanded.value = new Set()
}

function tenantStatusLabel(status: string): string {
  const key = `organizationTree.tenantStatus.${status}`
  const translated = t(key)
  return translated === key ? status : translated
}

function tenantStatusClass(status: string): string {
  if (status === 'active') return 'bg-emerald-50 text-emerald-800 ring-emerald-200/80'
  if (status === 'pending') return 'bg-amber-50 text-amber-900 ring-amber-200/80'
  if (status === 'suspended') return 'bg-orange-50 text-orange-900 ring-orange-200/80'
  if (status === 'archived') return 'bg-slate-100 text-slate-700 ring-slate-200/80'
  return 'bg-brand-bg text-brand-text-secondary ring-brand-border'
}

watch(
  units,
  (list) => {
    if (didAutoExpand.value || list.length === 0) return
    // First view: open top-level branches so the structure is readable immediately.
    expanded.value = new Set(list.map((unit) => unit.id))
    didAutoExpand.value = true
  },
  { immediate: true },
)
</script>

<template>
  <div class="space-y-5">
    <AppPageHeader
      :title="t('organizationTree.title')"
      :subtitle="t('organizationTree.subtitle')"
    >
      <template #actions>
        <button
          type="button"
          class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3.5 text-sm font-semibold text-brand-text shadow-sm transition hover:bg-brand-bg disabled:opacity-60"
          :disabled="isFetching"
          @click="() => refetch()"
        >
          <LoaderCircle v-if="isFetching" class="h-4 w-4 animate-spin" />
          <RefreshCw v-else class="h-4 w-4 text-brand-primary" :stroke-width="1.9" />
          {{ t('organizationTree.refresh') }}
        </button>
      </template>
    </AppPageHeader>

    <div
      v-if="isLoading"
      class="flex min-h-[280px] flex-col items-center justify-center gap-3 rounded-3xl border border-dashed border-brand-border bg-brand-surface/70 text-brand-text-muted"
    >
      <LoaderCircle class="h-6 w-6 animate-spin text-brand-primary" />
      <p class="text-sm font-medium">{{ t('organizationTree.loading') }}</p>
    </div>

    <div
      v-else-if="isError"
      class="rounded-3xl border border-red-200 bg-red-50 px-5 py-6 text-sm text-red-900"
    >
      <p class="font-semibold">{{ t('organizationTree.error') }}</p>
      <button
        type="button"
        class="mt-3 inline-flex h-9 items-center rounded-xl bg-white px-3 text-xs font-bold text-red-800 ring-1 ring-red-200"
        @click="() => refetch()"
      >
        {{ t('organizationTree.retry') }}
      </button>
    </div>

    <template v-else-if="tree">
      <section
        class="overflow-hidden rounded-3xl border border-brand-border bg-brand-surface shadow-[0_10px_30px_-24px_rgba(6,78,59,0.45)]"
      >
        <div
          class="border-b border-brand-border/80 bg-gradient-to-l from-brand-primary/[0.07] via-brand-surface to-brand-gold/[0.08] px-5 py-5 sm:px-6"
        >
          <div class="flex flex-col gap-5 lg:flex-row lg:items-stretch lg:justify-between">
            <div class="flex min-w-0 items-start gap-4">
              <div
                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brand-primary text-white shadow-lg shadow-brand-primary/25"
              >
                <Network class="h-6 w-6" :stroke-width="1.8" />
              </div>
              <div class="min-w-0">
                <p class="text-xs font-semibold tracking-wide text-brand-text-muted">
                  {{ t('organizationTree.tenant') }}
                </p>
                <h2 class="mt-1 truncate text-xl font-extrabold text-brand-text sm:text-2xl">
                  {{ tree.tenant.name }}
                </h2>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                  <span
                    class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1"
                    :class="tenantStatusClass(tree.tenant.status)"
                  >
                    {{ tenantStatusLabel(tree.tenant.status) }}
                  </span>
                  <span
                    class="rounded-full bg-white/80 px-2.5 py-0.5 font-mono text-[11px] text-brand-text-secondary ring-1 ring-brand-border"
                    dir="ltr"
                  >
                    {{ tree.tenant.code }}
                  </span>
                </div>
              </div>
            </div>

            <div
              class="grid grid-cols-3 gap-2 sm:min-w-[280px] sm:gap-3"
              :aria-label="t('organizationTree.statsLabel')"
            >
              <div class="rounded-2xl bg-white/85 px-3 py-3 text-center ring-1 ring-brand-border/70">
                <Building2 class="mx-auto h-4 w-4 text-brand-primary" />
                <p class="mt-1.5 text-lg font-extrabold text-brand-text">{{ stats.unitCount }}</p>
                <p class="text-[11px] font-medium text-brand-text-muted">
                  {{ t('organizationTree.statUnits') }}
                </p>
              </div>
              <div class="rounded-2xl bg-white/85 px-3 py-3 text-center ring-1 ring-brand-border/70">
                <Users class="mx-auto h-4 w-4 text-brand-primary" />
                <p class="mt-1.5 text-lg font-extrabold text-brand-text">{{ stats.employeeCount }}</p>
                <p class="text-[11px] font-medium text-brand-text-muted">
                  {{ t('organizationTree.statEmployees') }}
                </p>
              </div>
              <div class="rounded-2xl bg-white/85 px-3 py-3 text-center ring-1 ring-brand-border/70">
                <FolderTree class="mx-auto h-4 w-4 text-brand-primary" />
                <p class="mt-1.5 text-lg font-extrabold text-brand-text">{{ stats.positionCount }}</p>
                <p class="text-[11px] font-medium text-brand-text-muted">
                  {{ t('organizationTree.statPositions') }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="grid gap-0 lg:grid-cols-[minmax(240px,300px)_minmax(0,1fr)]">
          <aside class="border-b border-brand-border p-5 lg:border-b-0 lg:border-e lg:p-6">
            <div class="mb-3 flex items-center gap-2">
              <UserRound class="h-4 w-4 text-brand-primary" :stroke-width="1.85" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('organizationTree.ownership') }}</h3>
            </div>

            <div
              v-if="tree.ownership.owner"
              class="rounded-2xl border border-brand-border/80 bg-gradient-to-br from-brand-bg to-white p-4"
            >
              <div class="flex items-center gap-3">
                <div
                  class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-primary-soft text-sm font-bold text-brand-primary"
                >
                  {{ tree.ownership.owner.name.slice(0, 1) }}
                </div>
                <div class="min-w-0">
                  <p class="truncate font-bold text-brand-text">{{ tree.ownership.owner.name }}</p>
                  <p class="truncate text-xs text-brand-text-muted" dir="ltr">
                    {{ tree.ownership.owner.email }}
                  </p>
                </div>
              </div>
              <p class="mt-3 text-[11px] leading-relaxed text-brand-text-secondary">
                {{ t('organizationTree.ownerHint') }}
              </p>
            </div>
            <div
              v-else
              class="rounded-2xl border border-dashed border-brand-border bg-brand-bg/50 px-4 py-5 text-sm text-brand-text-muted"
            >
              {{ t('organizationTree.noOwner') }}
            </div>
          </aside>

          <section class="min-w-0 p-5 sm:p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <div
                  class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-primary-soft text-brand-primary"
                >
                  <FolderTree class="h-4 w-4" :stroke-width="1.85" />
                </div>
                <div>
                  <h3 class="text-sm font-extrabold text-brand-text">
                    {{ t('organizationTree.structure') }}
                  </h3>
                  <p class="text-[11px] text-brand-text-muted">
                    {{ t('organizationTree.structureHint') }}
                  </p>
                </div>
              </div>
              <div class="flex gap-2">
                <button
                  type="button"
                  class="h-9 rounded-xl border border-brand-border bg-white px-3 text-xs font-semibold text-brand-text transition hover:bg-brand-bg"
                  @click="expandAll(units)"
                >
                  {{ t('organizationTree.expandAll') }}
                </button>
                <button
                  type="button"
                  class="h-9 rounded-xl border border-brand-border bg-white px-3 text-xs font-semibold text-brand-text transition hover:bg-brand-bg"
                  @click="collapseAll"
                >
                  {{ t('organizationTree.collapseAll') }}
                </button>
              </div>
            </div>

            <div
              v-if="units.length === 0"
              class="rounded-2xl border border-dashed border-brand-border bg-brand-bg/40 px-4 py-10 text-center"
            >
              <Building2 class="mx-auto h-8 w-8 text-brand-text-muted" />
              <p class="mt-3 text-sm font-medium text-brand-text-muted">
                {{ t('organizationTree.emptyUnits') }}
              </p>
            </div>

            <div
              v-else
              class="rounded-2xl border border-brand-border/80 bg-[linear-gradient(180deg,rgba(246,248,247,0.65),rgba(255,255,255,0.9))] p-2 sm:p-3"
            >
              <ul class="space-y-1.5" role="tree" :aria-label="t('organizationTree.structure')">
                <OrganizationTreeProjectionNode
                  v-for="unit in units"
                  :key="unit.id"
                  :unit="unit"
                  :depth="0"
                  :expanded="expanded"
                  @toggle="toggle"
                />
              </ul>
            </div>
          </section>
        </div>
      </section>
    </template>
  </div>
</template>
