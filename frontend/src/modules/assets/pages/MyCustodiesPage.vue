<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import AppPageHeader from '@/shared/components/AppPageHeader.vue'

import { useMyCustodiesQuery } from '../queries/useCustodiesQuery'
import {
  custodyStatusBadgeClass,
  custodyStatusDotClass,
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
    <AppPageHeader
      :title="t('assets.myCustodies.title')"
      :subtitle="t('assets.myCustodies.subtitle')"
      :meta="meta ? t('assets.myCustodies.count', { count: meta.total }) : undefined"
    />

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
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
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('assets.myCustodies.empty') }}
    </div>
    <template v-else>
      <div
        class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)] lg:block"
      >
        <div class="overflow-x-auto">
          <table class="min-w-full border-separate border-spacing-0 text-sm">
            <thead>
              <tr class="bg-[#F4F6F5]">
                <th
                  class="whitespace-nowrap border-b border-s-[3px] border-brand-border border-s-transparent px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text"
                >
                  {{ t('assets.columns.custodyNumber') }}
                </th>
                <th
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text"
                >
                  {{ t('assets.columns.asset') }}
                </th>
                <th
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text"
                >
                  {{ t('assets.columns.status') }}
                </th>
                <th
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text"
                >
                  {{ t('assets.columns.assignedAt') }}
                </th>
                <th
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text"
                >
                  {{ t('assets.columns.expectedReturnAt') }}
                </th>
                <th
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text"
                >
                  {{ t('assets.columns.returnedAt') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(custody, index) in custodies"
                :key="custody.id"
                class="group cursor-pointer"
                :class="index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'"
                @click="openAsset(custody.asset?.id)"
              >
                <td
                  class="whitespace-nowrap border-b border-s-[3px] border-brand-border/80 border-s-transparent px-5 py-3.5 transition-colors duration-150 group-hover:border-s-brand-primary group-hover:bg-[#EDF6F1]"
                >
                  <span
                    class="inline-flex items-center rounded-lg border border-brand-border bg-brand-bg px-2.5 py-1 font-mono text-[12px] font-bold tracking-wide text-brand-primary-dark"
                    dir="ltr"
                    >{{ custody.custody_number }}</span
                  >
                </td>
                <td
                  class="border-b border-brand-border/80 px-5 py-3.5 transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  <div class="font-semibold text-brand-text">{{ custody.asset?.name || '—' }}</div>
                  <div class="font-mono text-xs text-brand-text">
                    {{ custody.asset?.asset_number || '' }}
                  </div>
                </td>
                <td
                  class="border-b border-brand-border/80 px-5 py-3.5 transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  <span
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold"
                    :class="custodyStatusBadgeClass(custody.status)"
                  >
                    <span
                      class="h-1.5 w-1.5 rounded-full"
                      :class="custodyStatusDotClass(custody.status)"
                    />
                    {{ t(`assets.custodyStatus.${custody.status}`) }}
                  </span>
                  <span
                    v-if="custody.is_overdue"
                    class="ms-2 rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-800 ring-1 ring-inset ring-red-200/80"
                  >
                    {{ t('assets.myCustodies.overdue') }}
                  </span>
                </td>
                <td
                  class="border-b border-brand-border/80 px-5 py-3.5 text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  {{ formatDateTime(custody.assigned_at) }}
                </td>
                <td
                  class="border-b border-brand-border/80 px-5 py-3.5 text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  {{ formatDateTime(custody.expected_return_at) }}
                </td>
                <td
                  class="border-b border-brand-border/80 px-5 py-3.5 text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
                >
                  {{ formatDateTime(custody.returned_at) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="space-y-3 lg:hidden">
        <article
          v-for="custody in custodies"
          :key="custody.id"
          class="rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)] transition active:bg-brand-bg"
          @click="openAsset(custody.asset?.id)"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-mono text-xs font-bold text-brand-primary-dark" dir="ltr">
                {{ custody.custody_number }}
              </p>
              <h3 class="mt-1 truncate font-bold text-brand-text">
                {{ custody.asset?.name || '—' }}
              </h3>
              <p class="mt-0.5 font-mono text-xs text-brand-text-muted" dir="ltr">
                {{ custody.asset?.asset_number }}
              </p>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-1">
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold"
                :class="custodyStatusBadgeClass(custody.status)"
              >
                <span
                  class="h-1.5 w-1.5 rounded-full"
                  :class="custodyStatusDotClass(custody.status)"
                />
                {{ t(`assets.custodyStatus.${custody.status}`) }}
              </span>
              <span
                v-if="custody.is_overdue"
                class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-800 ring-1 ring-inset ring-red-200/80"
              >
                {{ t('assets.myCustodies.overdue') }}
              </span>
            </div>
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-brand-text-secondary">
            <div>
              <dt>{{ t('assets.columns.assignedAt') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text">
                {{ formatDateTime(custody.assigned_at) }}
              </dd>
            </div>
            <div>
              <dt>{{ t('assets.columns.expectedReturnAt') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text">
                {{ formatDateTime(custody.expected_return_at) }}
              </dd>
            </div>
          </dl>
        </article>
      </div>
    </template>
  </div>
</template>
