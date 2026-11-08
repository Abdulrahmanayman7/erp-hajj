<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { Loader2 } from 'lucide-vue-next'

import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import CopyPreviewPanel from '../components/CopyPreviewPanel.vue'
import EntityKindCheckboxes from '../components/EntityKindCheckboxes.vue'
import {
  useCreateSeasonCopyPreviewMutation,
  useExecuteSeasonCopyMutation,
} from '../mutations/useSeasonCopyMutations'
import { useSeasonTemplatesQuery } from '../queries/useSeasonTemplatesQuery'
import type { CopyDecision, CopyMode, SeasonCopyPreview, SeasonCopyRun } from '../types/seasonCopy'
import { COPY_MODES } from '../types/seasonCopy'
import {
  buildPreviewPayload,
  buildResolutions,
  canExecuteCopy,
  canPreviewCopy,
  canShowExecuteButton,
  canViewCopyHistory,
  emptyWizardForm,
  hasApplicableChanges,
  isPreviewExpired,
  sourceSeasonRequired,
  unresolvedConflicts,
  validateWizardForm,
} from '../validation/seasonCopyValidation'
import {
  canShowTemplateSource,
  filterApprovedEntityKinds,
  mapSeasonalTemplatesErrorCode,
} from '../validation/seasonTemplateValidation'

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()
const { confirm } = useConfirm()

const form = reactive(emptyWizardForm())
const fieldErrors = reactive<Record<string, string>>({})
const formError = ref('')
const preview = ref<SeasonCopyPreview | null>(null)
const decisions = ref<Record<string, CopyDecision | ''>>({})
const runResult = ref<SeasonCopyRun | null>(null)
const staleNotice = ref(false)

/* Re-evaluated so an expiring preview disables execution without a reload. */
const now = ref(new Date())
let tick: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  tick = setInterval(() => {
    now.value = new Date()
  }, 15_000)
})
onUnmounted(() => {
  if (tick) clearInterval(tick)
})

/* ----------------------------------------------------------------- lookups */
const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const seasons = computed(() => seasonsQuery.data.value?.data ?? [])

function seasonOption(season: { id: number; name: string; code: string }): AppSelectOption {
  return { value: season.id, label: `${season.name} (${season.code})` }
}

const sourceOptions = computed<AppSelectOption[]>(() => [
  {
    value: '',
    label: sourceSeasonOptional.value ? t('seasonalTemplates.copy.noSourceSeason') : t('seasonalTemplates.copy.selectSeason'),
  },
  ...seasons.value.filter((s) => s.id !== form.target_season_id).map(seasonOption),
])

/** Backend requires the target to be a draft season. */
const targetOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonalTemplates.copy.selectDraftSeason') },
  ...seasons.value
    .filter((s) => s.status === 'draft' && s.id !== form.source_season_id)
    .map(seasonOption),
])

const templateSourceAllowed = computed(() => canShowTemplateSource(can))
const templatesQuery = useSeasonTemplatesQuery(
  { status: 'approved', per_page: 100 },
  () => form.mode === 'from_template' && templateSourceAllowed.value,
)
const templates = computed(() => templatesQuery.data.value?.data ?? [])
const templateOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonalTemplates.copy.selectTemplate') },
  ...templates.value.map((tpl) => ({ value: tpl.id, label: `${tpl.name} (${tpl.template_number})` })),
])
const selectedTemplate = computed(() => templates.value.find((tpl) => tpl.id === form.source_template_id) ?? null)

const availableKinds = computed<readonly string[] | undefined>(() =>
  form.mode === 'from_template' && selectedTemplate.value
    ? filterApprovedEntityKinds(selectedTemplate.value.items.map((item) => item.entity_kind))
    : undefined,
)

/** Hint only: the server decides whether a live source season is needed. */
const sourceSeasonOptional = computed(
  () => form.mode === 'from_template' && !sourceSeasonRequired(form, selectedTemplate.value),
)

const modeOptions = computed<AppSelectOption[]>(() =>
  COPY_MODES.map((mode) => ({ value: mode, label: t(`seasonalTemplates.copy.mode.${mode}`) })),
)

const seasonNames = computed(() =>
  Object.fromEntries(seasons.value.map((s) => [s.id, s.name])) as Record<number, string>,
)

/* ----------------------------------------------------------------- form handlers */
function resetPreview(): void {
  preview.value = null
  decisions.value = {}
}

function setMode(value: string | number | null): void {
  form.mode = (value === 'from_template' ? 'from_template' : 'from_season') as CopyMode
  form.source_template_id = ''
  form.entity_kinds = []
}

function setNumber(key: 'source_season_id' | 'target_season_id' | 'source_template_id', value: string | number | null): void {
  form[key] = value === '' || value == null ? '' : Number(value)
}

watch(
  () => [form.mode, form.source_season_id, form.source_template_id, form.target_season_id, [...form.entity_kinds]],
  () => {
    resetPreview()
    staleNotice.value = false
    runResult.value = null
  },
)

/* Selecting a template defaults the kinds to the template's kinds. */
watch(
  () => form.source_template_id,
  () => {
    form.entity_kinds = selectedTemplate.value
      ? filterApprovedEntityKinds(selectedTemplate.value.items.map((item) => item.entity_kind))
      : []
  },
)

watch(
  templates,
  () => {
    const queried = Number(route.query.template)
    if (Number.isFinite(queried) && queried > 0 && form.source_template_id === '' && templates.value.some((tpl) => tpl.id === queried)) {
      form.source_template_id = queried
    }
  },
)

onMounted(() => {
  const queried = Number(route.query.template)
  if (Number.isFinite(queried) && queried > 0 && templateSourceAllowed.value) {
    form.mode = 'from_template'
  }
})

/* ----------------------------------------------------------------- preview */
const previewMutation = useCreateSeasonCopyPreviewMutation()
const executeMutation = useExecuteSeasonCopyMutation()
const previewing = computed(() => previewMutation.isPending.value)
const executing = computed(() => executeMutation.isPending.value)

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('seasonalTemplates.errors.generic')
  const mapped = mapSeasonalTemplatesErrorCode(error.code)
  return mapped !== 'generic'
    ? t(`seasonalTemplates.errors.${mapped}`)
    : error.message || t('seasonalTemplates.errors.generic')
}

async function createPreview(): Promise<void> {
  formError.value = ''
  runResult.value = null
  staleNotice.value = false
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const local = validateWizardForm(form, selectedTemplate.value)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length || !canPreviewCopy(can)) return
  try {
    preview.value = await previewMutation.mutateAsync(buildPreviewPayload(form))
    decisions.value = {}
    toast.success(t('seasonalTemplates.toasts.previewCreated'))
  } catch (error) {
    preview.value = null
    formError.value = apiMessage(error)
  }
}

/* ----------------------------------------------------------------- execute */
const previewExpired = computed(() => (preview.value ? isPreviewExpired(preview.value, now.value) : false))
const unresolvedCount = computed(() => unresolvedConflicts(preview.value, decisions.value).length)
const executeEnabled = computed(() =>
  canExecuteCopy({ can, preview: preview.value, decisions: decisions.value, now: now.value }),
)
const nothingToApply = computed(() => !!preview.value && !hasApplicableChanges(preview.value))

async function execute(): Promise<void> {
  const current = preview.value
  if (!current || !executeEnabled.value) return
  const ok = await confirm({
    title: t('seasonalTemplates.copy.confirm.title'),
    message: t('seasonalTemplates.copy.confirm.body'),
    confirmLabel: t('seasonalTemplates.copy.execute'),
    cancelLabel: t('seasonalTemplates.common.cancel'),
  })
  if (!ok) return
  formError.value = ''
  try {
    const run = await executeMutation.mutateAsync({
      preview_token: current.preview_token,
      content_hash: current.content_hash,
      resolutions: buildResolutions(current, decisions.value),
    })
    runResult.value = run
    resetPreview()
    toast.success(t('seasonalTemplates.toasts.executed'))
  } catch (error) {
    if (error instanceof ApiError && error.code === 'SEASON_COPY_PREVIEW_STALE') {
      resetPreview()
      staleNotice.value = true
    }
    formError.value = apiMessage(error)
  }
}

function formatWhen(iso: string | null | undefined): string {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
  } catch {
    return iso
  }
}
</script>

<template>
  <div class="space-y-6" data-testid="season-safe-copy-page" dir="rtl">
    <AppPageHeader :title="t('seasonalTemplates.copy.title')" :subtitle="t('seasonalTemplates.copy.subtitle')">
      <template #actions>
        <button
          v-if="canViewCopyHistory(can)"
          type="button"
          class="inline-flex h-11 items-center gap-2 rounded-xl border border-brand-border px-4 text-sm font-semibold"
          @click="router.push('/app/season-copy-runs')"
        >
          {{ t('seasonalTemplates.copy.viewHistory') }}
        </button>
      </template>
    </AppPageHeader>

    <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-start text-sm text-amber-900" data-testid="copy-safety-note">
      {{ t('seasonalTemplates.copy.safetyNote') }}
    </p>

    <p
      v-if="!canPreviewCopy(can)"
      class="rounded-xl border border-brand-border bg-brand-surface px-4 py-3 text-start text-sm text-brand-text-muted"
      data-testid="copy-no-preview-permission"
    >
      {{ t('seasonalTemplates.copy.noPreviewPermission') }}
    </p>

    <section class="space-y-5 rounded-2xl border border-brand-border bg-brand-surface p-5 text-start" data-testid="copy-config">
      <h2 class="text-base font-bold text-brand-text">{{ t('seasonalTemplates.copy.step1') }}</h2>

      <div class="grid gap-4 md:grid-cols-2">
        <div class="space-y-1.5">
          <span class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.copy.mode.label') }}</span>
          <AppSelect :model-value="form.mode" :options="modeOptions" @update:model-value="setMode" />
        </div>

        <div v-if="form.mode === 'from_template'" class="space-y-1.5">
          <span class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.copy.sourceTemplate') }}</span>
          <AppSelect
            :model-value="form.source_template_id"
            :options="templateOptions"
            :disabled="!templateSourceAllowed"
            @update:model-value="setNumber('source_template_id', $event)"
          />
          <p v-if="!templateSourceAllowed" class="text-xs text-brand-text-muted">{{ t('seasonalTemplates.copy.templatesNeedView') }}</p>
          <p v-if="fieldErrors.source_template_id" class="text-xs text-red-600">{{ t(`seasonalTemplates.validation.${fieldErrors.source_template_id}`) }}</p>
        </div>

        <div class="space-y-1.5">
          <span class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.copy.sourceSeason') }}</span>
          <AppSelect
            :model-value="form.source_season_id"
            :options="sourceOptions"
            searchable
            :loading="seasonsQuery.isLoading.value"
            @update:model-value="setNumber('source_season_id', $event)"
          />
          <p
            v-if="sourceSeasonOptional"
            class="text-xs text-emerald-800"
            data-testid="copy-source-optional"
          >
            {{ t('seasonalTemplates.copy.sourceSeasonOptionalHint') }}
          </p>
          <p v-else-if="form.mode === 'from_template'" class="text-xs text-brand-text-muted">{{ t('seasonalTemplates.copy.sourceSeasonTemplateHint') }}</p>
          <p v-if="fieldErrors.source_season_id" class="text-xs text-red-600">{{ t(`seasonalTemplates.validation.${fieldErrors.source_season_id}`) }}</p>
        </div>

        <div class="space-y-1.5">
          <span class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.copy.targetSeason') }}</span>
          <AppSelect
            :model-value="form.target_season_id"
            :options="targetOptions"
            searchable
            :loading="seasonsQuery.isLoading.value"
            @update:model-value="setNumber('target_season_id', $event)"
          />
          <p class="text-xs text-brand-text-muted">{{ t('seasonalTemplates.copy.targetHint') }}</p>
          <p v-if="fieldErrors.target_season_id" class="text-xs text-red-600">{{ t(`seasonalTemplates.validation.${fieldErrors.target_season_id}`) }}</p>
        </div>
      </div>

      <p v-if="seasonsQuery.isError.value" class="text-sm text-red-700" role="alert">{{ t('seasonalTemplates.copy.seasonsLoadError') }}</p>

      <div class="space-y-2">
        <p class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.copy.entityKinds') }}</p>
        <p class="text-xs text-brand-text-muted">{{ t('seasonalTemplates.copy.entityKindsHint') }}</p>
        <EntityKindCheckboxes
          v-model="form.entity_kinds"
          :available="availableKinds"
          :disabled="previewing || executing"
          :error-message="fieldErrors.entity_kinds ? t(`seasonalTemplates.validation.${fieldErrors.entity_kinds}`) : ''"
        />
      </div>

      <div class="flex justify-end">
        <button
          type="button"
          class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-5 text-sm font-semibold text-white disabled:opacity-50"
          :disabled="!canPreviewCopy(can) || previewing || executing"
          data-testid="copy-preview-btn"
          @click="createPreview"
        >
          <Loader2 v-if="previewing" class="h-4 w-4 animate-spin" />
          {{ previewing ? t('seasonalTemplates.copy.previewing') : t('seasonalTemplates.copy.createPreview') }}
        </button>
      </div>
    </section>

    <p v-if="formError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-start text-sm text-red-700" role="alert" data-testid="copy-error">
      {{ formError }}
    </p>
    <p v-if="staleNotice" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-start text-sm text-amber-900" role="status" data-testid="copy-stale">
      {{ t('seasonalTemplates.copy.staleNotice') }}
    </p>

    <section v-if="preview" class="space-y-4 rounded-2xl border border-brand-border bg-brand-surface p-5 text-start" data-testid="copy-preview-section">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-base font-bold text-brand-text">{{ t('seasonalTemplates.copy.step2') }}</h2>
        <p class="text-xs text-brand-text-muted">
          {{ t('seasonalTemplates.copy.expiresAt', { time: formatWhen(preview.expires_at) }) }}
        </p>
      </div>

      <p v-if="previewExpired" class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-700" role="alert" data-testid="copy-expired">
        {{ t('seasonalTemplates.copy.previewExpired') }}
      </p>
      <p v-else-if="nothingToApply" class="rounded-xl border border-brand-border bg-brand-bg px-3.5 py-2.5 text-sm text-brand-text-muted" data-testid="copy-nothing">
        {{ t('seasonalTemplates.copy.nothingToApply') }}
      </p>

      <CopyPreviewPanel
        :preview="preview"
        :decisions="decisions"
        :can-resolve="canShowExecuteButton(can)"
        :disabled="executing"
        @update:decisions="decisions = $event"
      />

      <p v-if="!canShowExecuteButton(can)" class="text-xs text-brand-text-muted" data-testid="copy-no-execute-permission">
        {{ t('seasonalTemplates.copy.noExecutePermission') }}
      </p>

      <div class="flex flex-wrap items-center justify-end gap-3">
        <button
          type="button"
          class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold"
          :disabled="executing"
          @click="resetPreview"
        >
          {{ t('seasonalTemplates.copy.discardPreview') }}
        </button>
        <button
          v-if="canShowExecuteButton(can)"
          type="button"
          class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-5 text-sm font-semibold text-white disabled:opacity-50"
          :disabled="!executeEnabled || executing"
          :title="unresolvedCount > 0 ? t('seasonalTemplates.copy.unresolved', { count: unresolvedCount }) : undefined"
          data-testid="copy-execute-btn"
          @click="execute"
        >
          <Loader2 v-if="executing" class="h-4 w-4 animate-spin" />
          {{ executing ? t('seasonalTemplates.copy.executing') : t('seasonalTemplates.copy.execute') }}
        </button>
      </div>
    </section>

    <section v-if="runResult" class="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-start" data-testid="copy-result">
      <h2 class="text-base font-bold text-emerald-950">{{ t('seasonalTemplates.copy.resultTitle') }}</h2>
      <p class="font-mono text-xs text-emerald-900" dir="ltr">{{ runResult.run_number }}</p>
      <div v-if="runResult.counts" class="grid gap-3 sm:grid-cols-4">
        <div v-for="key in ['added', 'changed', 'skipped', 'excluded'] as const" :key="key" class="rounded-xl border border-emerald-200 bg-white p-3">
          <p class="text-xs font-semibold text-brand-text-muted">{{ t(`seasonalTemplates.runs.result.${key}`) }}</p>
          <p class="mt-1 text-xl font-bold text-brand-text">{{ runResult.counts[key] }}</p>
        </div>
      </div>
      <p class="text-xs text-emerald-900">
        {{ t('seasonalTemplates.copy.resultTarget', { name: seasonNames[runResult.target_season_id] ?? `#${runResult.target_season_id}` }) }}
      </p>
      <button
        v-if="canViewCopyHistory(can)"
        type="button"
        class="inline-flex h-10 items-center rounded-xl border border-emerald-300 bg-white px-4 text-sm font-semibold text-emerald-950"
        @click="router.push('/app/season-copy-runs')"
      >
        {{ t('seasonalTemplates.copy.viewHistory') }}
      </button>
    </section>
  </div>
</template>
