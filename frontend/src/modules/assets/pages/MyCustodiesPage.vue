<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { useMyCustodiesQuery } from '../queries/useCustodiesQuery'
import {
  custodyStatusBadgeClass,
  formatDateTime,
  resolveAssetsListState,
  sortMyCustodies,
} from '../validation/assetValidation'

const { t } = useI18n()
const router = useRouter()

const { data, isLoading, isError, refetch } = useMyCustodiesQuery(
  computed(() => ({ per_page: 100 })),
)

const custodies = computed(() => sortMyCustodies(data.value?.data ?? []))
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveAssetsListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: custodies.value.length,
  }),
)

function openAsset(assetId: number | undefined | null): void {
  if (!assetId) return
  router.push(`/app/assets/${assetId}`)
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-[1.75rem] font-bold">{{ t('assets.myCustodies.title') }}</h2>
      <p class="mt-1.5 text-sm text-brand-text-secondary">{{ t('assets.myCustodies.subtitle') }}</p>
    </div>

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('assets.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('assets.errors.load') }}</p>
      <button type="button" class="mt-3 underline" @click="() => refetch()">
        {{ t('assets.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('assets.myCustodies.empty') }}
    </div>
    <template v-else>
      <div class="hidden overflow-hidden rounded-2xl border bg-brand-surface md:block">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-[#F4F6F5]">
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.custodyNumber') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.asset') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.status') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.assignedAt') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.expectedReturnAt') }}
              </th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
                {{ t('assets.columns.returnedAt') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="custody in custodies"
              :key="custody.id"
              class="cursor-pointer border-t hover:bg-brand-bg/60"
              @click="openAsset(custody.asset?.id)"
            >
              <td class="px-5 py-3 font-mono text-xs">{{ custody.custody_number }}</td>
              <td class="px-5 py-3">
                <div class="font-semibold">{{ custody.asset?.name || '—' }}</div>
                <div class="font-mono text-xs text-brand-text-muted">
                  {{ custody.asset?.asset_number || '' }}
                </div>
              </td>
              <td class="px-5 py-3">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="custodyStatusBadgeClass(custody.status)"
                >
                  {{ t(`assets.custodyStatus.${custody.status}`) }}
                </span>
                <span
                  v-if="custody.is_overdue"
                  class="ms-2 rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-800"
                >
                  {{ t('assets.myCustodies.overdue') }}
                </span>
              </td>
              <td class="px-5 py-3">{{ formatDateTime(custody.assigned_at) }}</td>
              <td class="px-5 py-3">{{ formatDateTime(custody.expected_return_at) }}</td>
              <td class="px-5 py-3">{{ formatDateTime(custody.returned_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="space-y-3 md:hidden">
        <article
          v-for="custody in custodies"
          :key="custody.id"
          class="rounded-2xl border bg-brand-surface p-4"
          @click="openAsset(custody.asset?.id)"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="font-mono text-xs text-brand-text-muted">{{ custody.custody_number }}</p>
              <h3 class="font-bold">{{ custody.asset?.name || '—' }}</h3>
              <p class="font-mono text-xs text-brand-text-muted">
                {{ custody.asset?.asset_number }}
              </p>
            </div>
            <span
              class="rounded-full px-2 py-0.5 text-xs font-semibold"
              :class="custodyStatusBadgeClass(custody.status)"
            >
              {{ t(`assets.custodyStatus.${custody.status}`) }}
            </span>
          </div>
          <p class="mt-3 text-xs text-brand-text-secondary">
            {{ t('assets.columns.assignedAt') }}: {{ formatDateTime(custody.assigned_at) }}
          </p>
        </article>
      </div>

      <p v-if="meta" class="text-center text-xs text-brand-text-muted">
        {{ t('assets.myCustodies.count', { count: meta.total }) }}
      </p>
    </template>
  </div>
</template>
