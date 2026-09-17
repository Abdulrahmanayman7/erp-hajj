<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { FolderTree, LoaderCircle, Network, UserRound } from 'lucide-vue-next'

import AppPageHeader from '@/shared/components/AppPageHeader.vue'

import OrganizationTreeProjectionNode from '../components/OrganizationTreeProjectionNode.vue'
import type { OrganizationTreeUnit } from '../api/organizationTreeApi'
import { useOrganizationTreeQuery } from '../queries/useOrganizationTreeQuery'

const { t } = useI18n()
const { data, isLoading, isError, refetch, isFetching } = useOrganizationTreeQuery()

const expanded = ref<Set<number>>(new Set())

const tree = computed(() => data.value)
const units = computed(() => tree.value?.organization.units ?? [])

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
          class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-medium text-brand-text transition hover:bg-brand-bg disabled:opacity-60"
          :disabled="isFetching"
          @click="() => refetch()"
        >
          <LoaderCircle v-if="isFetching" class="h-4 w-4 animate-spin" />
          {{ t('organizationTree.refresh') }}
        </button>
      </template>
    </AppPageHeader>

    <div v-if="isLoading" class="flex items-center justify-center gap-2 py-16 text-brand-text-muted">
      <LoaderCircle class="h-5 w-5 animate-spin" />
      {{ t('organizationTree.loading') }}
    </div>

    <div
      v-else-if="isError"
      class="rounded-2xl border border-red-200 bg-red-50 px-4 py-6 text-sm text-red-800"
    >
      {{ t('organizationTree.error') }}
      <button type="button" class="ms-2 font-semibold underline" @click="() => refetch()">
        {{ t('organizationTree.retry') }}
      </button>
    </div>

    <template v-else-if="tree">
      <section class="rounded-2xl border border-brand-border bg-brand-surface p-5">
        <div class="flex items-start gap-3">
          <div
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-primary/10 text-brand-primary"
          >
            <Network class="h-5 w-5" :stroke-width="1.75" />
          </div>
          <div class="min-w-0">
            <p class="text-xs font-medium text-brand-text-muted">
              {{ t('organizationTree.tenant') }}
            </p>
            <h2 class="truncate text-lg font-bold text-brand-text">{{ tree.tenant.name }}</h2>
            <p class="mt-0.5 font-mono text-sm text-brand-text-secondary" dir="ltr">
              {{ tree.tenant.code }} · {{ tree.tenant.status }}
            </p>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-brand-border bg-brand-surface p-5">
        <div class="mb-3 flex items-center gap-2">
          <UserRound class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
          <h3 class="text-sm font-bold text-brand-text">{{ t('organizationTree.ownership') }}</h3>
        </div>
        <div v-if="tree.ownership.owner" class="rounded-xl bg-brand-bg/70 px-4 py-3">
          <p class="font-semibold text-brand-text">{{ tree.ownership.owner.name }}</p>
          <p class="text-sm text-brand-text-muted" dir="ltr">{{ tree.ownership.owner.email }}</p>
        </div>
        <p v-else class="text-sm text-brand-text-muted">{{ t('organizationTree.noOwner') }}</p>
      </section>

      <section class="rounded-2xl border border-brand-border bg-brand-surface p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <FolderTree class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
            <h3 class="text-sm font-bold text-brand-text">
              {{ t('organizationTree.structure') }}
            </h3>
          </div>
          <div class="flex gap-2">
            <button
              type="button"
              class="h-9 rounded-lg border border-brand-border px-3 text-xs font-medium"
              @click="expandAll(units)"
            >
              {{ t('organizationTree.expandAll') }}
            </button>
            <button
              type="button"
              class="h-9 rounded-lg border border-brand-border px-3 text-xs font-medium"
              @click="collapseAll"
            >
              {{ t('organizationTree.collapseAll') }}
            </button>
          </div>
        </div>

        <p v-if="units.length === 0" class="text-sm text-brand-text-muted">
          {{ t('organizationTree.emptyUnits') }}
        </p>

        <ul v-else class="space-y-1" role="tree">
          <OrganizationTreeProjectionNode
            v-for="unit in units"
            :key="unit.id"
            :unit="unit"
            :depth="0"
            :expanded="expanded"
            @toggle="toggle"
          />
        </ul>
      </section>
    </template>
  </div>
</template>
