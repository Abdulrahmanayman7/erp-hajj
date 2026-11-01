<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { Plus } from 'lucide-vue-next'

import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'

import {
  createDemandGroup,
  listDemandGroups,
} from '../api/demandGroupsApi'
import type { DemandGroup, DemandGroupStatus } from '../types/demandGroups'

const { t } = useI18n()
const { can } = usePermissions()

const filters = reactive({
  status: '' as DemandGroupStatus | '',
  q: '',
  page: 1,
  per_page: 20,
})

const rows = ref<DemandGroup[]>([])
const meta = ref<Record<string, number>>({})
const isLoading = ref(false)
const isError = ref(false)
const createOpen = ref(false)
const createTitle = ref('')
const createBusy = ref(false)
const createError = ref('')

async function load(): Promise<void> {
  isLoading.value = true
  isError.value = false
  try {
    const result = await listDemandGroups(filters)
    rows.value = result.data
    meta.value = result.meta
  } catch {
    isError.value = true
  } finally {
    isLoading.value = false
  }
}

void load()

const canMutate = computed(() => can('purchase_requests.consolidate_planning'))

async function submitCreate(): Promise<void> {
  createBusy.value = true
  createError.value = ''
  try {
    await createDemandGroup({ title: createTitle.value.trim() })
    createOpen.value = false
    createTitle.value = ''
    await load()
  } catch (e) {
    createError.value = e instanceof Error ? e.message : t('purchaseDemandGroups.createFailed')
  } finally {
    createBusy.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold text-foreground">
          {{ t('purchaseDemandGroups.title') }}
        </h1>
        <p class="mt-1 text-sm text-muted-foreground">
          {{ t('purchaseDemandGroups.subtitle') }}
        </p>
        <p class="mt-2 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
          {{ t('purchaseDemandGroups.noRfqsBanner') }}
        </p>
      </div>
      <PermissionGuard permission="purchase_requests.consolidate_planning">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground"
          @click="createOpen = true"
        >
          <Plus class="h-4 w-4" />
          {{ t('purchaseDemandGroups.create') }}
        </button>
      </PermissionGuard>
    </header>

    <div class="flex flex-wrap gap-3">
      <input
        v-model="filters.q"
        type="search"
        class="rounded-md border px-3 py-2 text-sm"
        :placeholder="t('purchaseDemandGroups.search')"
        @change="load"
      />
      <select v-model="filters.status" class="rounded-md border px-3 py-2 text-sm" @change="load">
        <option value="">{{ t('purchaseDemandGroups.allStatuses') }}</option>
        <option value="draft">draft</option>
        <option value="active">active</option>
        <option value="frozen">frozen</option>
        <option value="closed">closed</option>
      </select>
    </div>

    <div v-if="isLoading" class="text-sm text-muted-foreground">{{ t('common.loading') }}</div>
    <div v-else-if="isError" class="text-sm text-destructive">{{ t('common.error') }}</div>
    <div v-else-if="rows.length === 0" class="text-sm text-muted-foreground">
      {{ t('purchaseDemandGroups.empty') }}
    </div>
    <table v-else class="w-full text-sm border-collapse">
      <thead>
        <tr class="border-b text-right text-muted-foreground">
          <th class="py-2 px-2 font-medium">{{ t('purchaseDemandGroups.number') }}</th>
          <th class="py-2 px-2 font-medium">{{ t('purchaseDemandGroups.name') }}</th>
          <th class="py-2 px-2 font-medium">{{ t('purchaseDemandGroups.status') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id" class="border-b">
          <td class="py-2 px-2">
            <RouterLink class="text-primary underline" :to="`/app/purchase-demand-groups/${row.id}`">
              {{ row.group_number }}
            </RouterLink>
          </td>
          <td class="py-2 px-2">{{ row.title }}</td>
          <td class="py-2 px-2">{{ row.status }}</td>
        </tr>
      </tbody>
    </table>

    <div
      v-if="createOpen && canMutate"
      class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
    >
      <div class="w-full max-w-md rounded-lg bg-background p-6 shadow-lg space-y-4">
        <h2 class="text-lg font-semibold">{{ t('purchaseDemandGroups.create') }}</h2>
        <input
          v-model="createTitle"
          class="w-full rounded-md border px-3 py-2 text-sm"
          :placeholder="t('purchaseDemandGroups.titleField')"
        />
        <p v-if="createError" class="text-sm text-destructive">{{ createError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="createOpen = false">
            {{ t('common.cancel') }}
          </button>
          <button
            type="button"
            class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50"
            :disabled="createBusy || createTitle.trim().length < 3"
            @click="submitCreate"
          >
            {{ t('common.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
