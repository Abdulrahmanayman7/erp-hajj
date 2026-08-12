<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, ExternalLink, RefreshCw } from 'lucide-vue-next'

import { useAuditLogQuery } from '../queries/useAuditQuery'
import {
  auditActorDisplay,
  auditEntityDisplay,
  auditEventLabel,
  auditFieldLabel,
  formatAuditValue,
  resolveAuditDeepLink,
} from '../utils/auditDisplay'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const id = computed(() => {
  const raw = Number(route.params.id)
  return Number.isFinite(raw) ? raw : null
})

const { data, isLoading, isError, refetch } = useAuditLogQuery(id)

const deepLink = computed(() => resolveAuditDeepLink(data.value?.deep_link ?? null))

const changeKeys = computed(() => {
  const before = data.value?.before_values ?? {}
  const after = data.value?.after_values ?? {}
  return Array.from(new Set([...Object.keys(before), ...Object.keys(after)]))
})

const metadataEntries = computed(() => Object.entries(data.value?.metadata ?? {}))

function formatTime(iso: string): string {
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      dateStyle: 'full',
      timeStyle: 'medium',
      timeZone: 'Asia/Riyadh',
    }).format(new Date(iso))
  } catch {
    return iso
  }
}
</script>

<template>
  <div class="space-y-6">
    <button
      type="button"
      class="inline-flex items-center gap-2 text-sm text-emerald-800 hover:underline"
      @click="router.push('/app/audit')"
    >
      <ArrowRight class="h-4 w-4" />
      {{ t('audit.backToList') }}
    </button>

    <section v-if="isLoading" class="space-y-3" aria-busy="true">
      <div class="h-24 animate-pulse rounded-xl bg-slate-100" />
      <div class="h-40 animate-pulse rounded-xl bg-slate-100" />
    </section>

    <section v-else-if="isError" class="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
      <p class="text-red-800">{{ t('audit.error') }}</p>
      <button type="button" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-red-700 px-4 py-2 text-sm text-white" @click="refetch()">
        <RefreshCw class="h-4 w-4" />
        {{ t('audit.retry') }}
      </button>
    </section>

    <template v-else-if="data">
      <header>
        <h1 class="text-2xl font-bold text-slate-900">{{ auditEventLabel(data.event_type) }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ data.event_type }}</p>
      </header>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">{{ t('audit.details.summary') }}</h2>
        <dl class="mt-3 grid gap-3 sm:grid-cols-2 text-sm">
          <div>
            <dt class="text-slate-500">{{ t('audit.columns.time') }}</dt>
            <dd class="font-medium text-slate-900">{{ formatTime(data.created_at) }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">{{ t('audit.details.correlation') }}</dt>
            <dd class="font-mono text-xs text-slate-800">{{ data.correlation_id }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">{{ t('audit.details.source') }}</dt>
            <dd>{{ data.source }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">{{ t('audit.details.contextType') }}</dt>
            <dd>{{ data.context_type }}</dd>
          </div>
        </dl>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">{{ t('audit.details.actor') }}</h2>
        <p class="mt-2 text-slate-900">{{ auditActorDisplay(data.actor_type, data.actor_label) }}</p>
        <p class="mt-1 text-xs text-slate-500">{{ data.actor_type }}</p>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">{{ t('audit.details.entity') }}</h2>
        <p class="mt-2 text-slate-900">{{ auditEntityDisplay(data) }}</p>
        <a
          v-if="deepLink"
          :href="deepLink"
          class="mt-3 inline-flex items-center gap-2 text-sm text-emerald-800 hover:underline"
          @click.prevent="router.push(deepLink)"
        >
          <ExternalLink class="h-4 w-4" />
          {{ t('audit.details.openEntity') }}
        </a>
        <p v-else class="mt-2 text-sm text-slate-500">{{ t('audit.details.entitySnapshotOnly') }}</p>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">{{ t('audit.details.changes') }}</h2>
        <div v-if="changeKeys.length === 0" class="mt-2 text-sm text-slate-500">{{ t('audit.details.noChanges') }}</div>
        <ul v-else class="mt-3 space-y-2 text-sm">
          <li v-for="key in changeKeys" :key="key" class="rounded-lg bg-slate-50 px-3 py-2">
            <div class="font-medium text-slate-800">{{ auditFieldLabel(key) }}</div>
            <div class="mt-1 text-slate-700">
              {{ formatAuditValue(data.before_values?.[key]) }}
              <span class="mx-2 text-slate-400">→</span>
              {{ formatAuditValue(data.after_values?.[key]) }}
            </div>
          </li>
        </ul>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">{{ t('audit.details.metadata') }}</h2>
        <div v-if="metadataEntries.length === 0" class="mt-2 text-sm text-slate-500">{{ t('audit.details.noMetadata') }}</div>
        <dl v-else class="mt-3 space-y-2 text-sm">
          <div v-for="[key, value] in metadataEntries" :key="key" class="grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)] gap-3">
            <dt class="text-slate-500">{{ auditFieldLabel(key) }}</dt>
            <dd class="break-words text-slate-800">{{ formatAuditValue(value) }}</dd>
          </div>
        </dl>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">{{ t('audit.details.context') }}</h2>
        <dl class="mt-3 grid gap-3 sm:grid-cols-2 text-sm">
          <div>
            <dt class="text-slate-500">IP</dt>
            <dd>{{ data.ip_address || '—' }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">User-Agent</dt>
            <dd class="break-all text-xs">{{ data.user_agent || '—' }}</dd>
          </div>
        </dl>
      </section>
    </template>
  </div>
</template>
