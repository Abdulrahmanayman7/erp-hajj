<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ChevronLeft, ChevronRight, Plus, Search } from 'lucide-vue-next'

import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import { ApiError } from '@/shared/api/http'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import TemplateFormDrawer from '../components/TemplateFormDrawer.vue'
import TemplateStatusBadge from '../components/TemplateStatusBadge.vue'
import { useCreateSeasonTemplateMutation } from '../mutations/useSeasonTemplateMutations'
import { useSeasonTemplatesQuery } from '../queries/useSeasonTemplatesQuery'
import type { ListSeasonTemplatesParams, TemplateFormState, TemplateStatus } from '../types/seasonTemplates'
import { TEMPLATE_STATUSES } from '../types/seasonTemplates'
import {
  buildCaptureSeason,
  buildTemplateItems,
  canShowCreateTemplate,
  emptyTemplateForm,
  mapSeasonalTemplatesErrorCode,
  resolveListState,
  validateTemplateForm,
} from '../validation/seasonTemplateValidation'

const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const { can } = usePermissions()

const filters = reactive({ search: '', status: '' as TemplateStatus | '', page: 1, per_page: 15 })
const committedSearch = useDebouncedRef(() => filters.search)

const params = computed<ListSeasonTemplatesParams>(() => ({
  q: committedSearch.value,
  status: filters.status,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch } = useSeasonTemplatesQuery(params)
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveListState({ isLoading: isLoading.value, isError: isError.value, count: rows.value.length }),
)

watch(
  () => [committedSearch.value, filters.status],
  () => {
    filters.page = 1
  },
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonalTemplates.templates.allStatuses') },
  ...TEMPLATE_STATUSES.map((s) => ({ value: s, label: t(`seasonalTemplates.status.${s}`) })),
])

const activeFilterCount = computed(() => (filters.status ? 1 : 0))

function resetFilters(): void {
  filters.search = ''
  filters.status = ''
}

/* ----------------------------------------------------------------- create drawer */
const drawerOpen = ref(false)
const form = reactive<TemplateFormState>(emptyTemplateForm())
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const createMutation = useCreateSeasonTemplateMutation()
const submitting = computed(() => createMutation.isPending.value)

const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const captureSeasons = computed<AppSelectOption[]>(() =>
  (seasonsQuery.data.value?.data ?? []).map((s) => ({ value: s.id, label: `${s.name} (${s.code})` })),
)

function openCreate(): void {
  Object.assign(form, emptyTemplateForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('seasonalTemplates.errors.generic')
  const mapped = mapSeasonalTemplatesErrorCode(error.code)
  return mapped !== 'generic'
    ? t(`seasonalTemplates.errors.${mapped}`)
    : error.message || t('seasonalTemplates.errors.generic')
}

async function submit(): Promise<void> {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const local = validateTemplateForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return
  try {
    const created = await createMutation.mutateAsync({
      name: form.name.trim(),
      description: form.description.trim() || null,
      items: buildTemplateItems(form),
      ...buildCaptureSeason(form),
    })
    toast.success(t('seasonalTemplates.toasts.created'))
    drawerOpen.value = false
    await router.push(`/app/season-templates/${created.id}`)
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

function formatWhen(iso: string | null): string {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', { dateStyle: 'medium' }).format(new Date(iso))
  } catch {
    return iso
  }
}
</script>

<template>
  <div class="space-y-6" data-testid="season-templates-page" dir="rtl">
    <AppPageHeader :title="t('seasonalTemplates.templates.title')" :subtitle="t('seasonalTemplates.templates.subtitle')">
      <template #actions>
        <PermissionGuard v-if="canShowCreateTemplate(can)" permission="season_templates.manage">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white"
            data-testid="template-create-btn"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            {{ t('seasonalTemplates.templates.add') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <AppMobileFilters
      v-model:search="filters.search"
      :search-placeholder="t('seasonalTemplates.templates.searchPlaceholder')"
      :active-count="activeFilterCount"
      @reset="resetFilters"
    >
      <template #desktop>
        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]">
          <div class="relative min-w-48 flex-1">
            <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted" :stroke-width="1.75" aria-hidden="true" />
            <input
              v-model="filters.search"
              type="search"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-start text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
              :placeholder="t('seasonalTemplates.templates.searchPlaceholder')"
              data-testid="templates-search"
            />
          </div>
          <div class="w-full sm:w-52">
            <AppSelect v-model="filters.status" :options="statusOptions" />
          </div>
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <AppSelect v-model="filters.status" :options="statusOptions" />
        </div>
      </template>
    </AppMobileFilters>

    <div v-if="listState === 'loading'" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted" data-testid="templates-loading">
      {{ t('seasonalTemplates.common.loading') }}
    </div>
    <div v-else-if="listState === 'error'" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center" data-testid="templates-error">
      <p class="text-sm text-red-700">{{ t('seasonalTemplates.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('seasonalTemplates.common.retry') }}
      </button>
    </div>
    <div v-else-if="listState === 'empty'" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted" data-testid="templates-empty">
      {{ t('seasonalTemplates.templates.empty') }}
    </div>
    <div v-else class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface" data-testid="templates-table">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-[#F4F6F5]">
            <tr>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.templates.columns.number') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.templates.columns.name') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.templates.columns.status') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.templates.columns.items') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.templates.columns.updated') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.id"
              class="cursor-pointer border-t border-brand-border/80 hover:bg-brand-bg/60"
              @click="router.push(`/app/season-templates/${row.id}`)"
            >
              <td class="px-5 py-3.5 text-start font-mono text-xs" dir="ltr">{{ row.template_number }}</td>
              <td class="px-5 py-3.5 text-start font-semibold text-brand-text">{{ row.name }}</td>
              <td class="px-5 py-3.5 text-start"><TemplateStatusBadge :status="row.status" /></td>
              <td class="px-5 py-3.5 text-start">{{ row.items.length }}</td>
              <td class="px-5 py-3.5 text-start text-xs text-brand-text-muted">{{ formatWhen(row.updated_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t border-brand-border px-5 py-3 text-sm">
        <button type="button" class="inline-flex h-9 items-center gap-1 rounded-lg px-3 disabled:opacity-40" :disabled="filters.page <= 1" :aria-label="t('common.prev')" @click="filters.page -= 1">
          <ChevronRight class="h-4 w-4" />
        </button>
        <span class="text-brand-text-muted">{{ filters.page }} / {{ meta.last_page }}</span>
        <button type="button" class="inline-flex h-9 items-center gap-1 rounded-lg px-3 disabled:opacity-40" :disabled="filters.page >= meta.last_page" :aria-label="t('common.next')" @click="filters.page += 1">
          <ChevronLeft class="h-4 w-4" />
        </button>
      </div>
    </div>

    <TemplateFormDrawer
      :open="drawerOpen"
      :editing="false"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="submitting"
      :capture-seasons="captureSeasons"
      @close="drawerOpen = false"
      @submit="submit"
      @update:form="Object.assign(form, $event)"
    />
  </div>
</template>
