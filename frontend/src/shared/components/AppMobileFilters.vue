<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Filter, Search, X } from 'lucide-vue-next'

import { useBodyScrollLock } from '@/shared/composables/useBodyScrollLock'

const props = withDefaults(
  defineProps<{
    search?: string
    searchPlaceholder?: string
    activeCount?: number
    /** When false, hide the mobile/tablet search field (filters-only pages). */
    showSearch?: boolean
    /** Optional primary apply CTA label (e.g. عرض X مهمة). */
    applyLabel?: string
  }>(),
  {
    search: '',
    searchPlaceholder: '',
    showSearch: true,
    applyLabel: undefined,
  },
)

const emit = defineEmits<{
  'update:search': [value: string]
  apply: []
  reset: []
}>()

const { t } = useI18n()
const open = ref(false)
useBodyScrollLock(open)

const filterLabel = computed(() => {
  const count = props.activeCount ?? 0
  return count > 0 ? t('shell.filtersWithCount', { count }) : t('shell.filters')
})

const resolvedApplyLabel = computed(
  () => props.applyLabel?.trim() || t('shell.applyFilters'),
)

function openSheet(): void {
  open.value = true
}

function closeSheet(): void {
  open.value = false
}

function applyAndClose(): void {
  emit('apply')
  closeSheet()
}

function resetAndClose(): void {
  emit('reset')
  closeSheet()
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && open.value) {
    event.preventDefault()
    closeSheet()
  }
}

watch(open, (isOpen) => {
  if (isOpen) {
    document.addEventListener('keydown', onKeydown)
    return
  }
  document.removeEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div class="space-y-3">
    <!--
      Mobile + Tablet (<1280): search + filter sheet (touch-first).
      Desktop (≥1280): dense inline toolbar via #desktop slot.
    -->
    <div class="flex items-center gap-2 xl:hidden">
      <div v-if="showSearch" class="relative min-w-0 flex-1">
        <Search
          class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
          :stroke-width="1.75"
        />
        <input
          :value="search"
          type="search"
          class="app-input app-input--search"
          :placeholder="searchPlaceholder"
          @input="emit('update:search', ($event.target as HTMLInputElement).value)"
        />
      </div>
      <button
        type="button"
        class="inline-flex h-11 min-w-11 shrink-0 items-center justify-center gap-2 rounded-[10px] border border-brand-border bg-brand-surface px-3 text-sm font-medium text-brand-text transition hover:bg-[var(--surface-subtle)]"
        :class="[
          { 'border-brand-primary/35 bg-brand-primary-soft text-brand-primary-dark': (activeCount ?? 0) > 0 },
          !showSearch ? 'w-full' : '',
        ]"
        @click="openSheet"
      >
        <Filter class="h-4 w-4 shrink-0" :stroke-width="2" aria-hidden="true" />
        <span class="truncate">{{ filterLabel }}</span>
      </button>
    </div>

    <div class="hidden xl:block">
      <slot name="desktop" />
    </div>

    <Teleport to="body">
      <div
        v-if="open"
        class="fixed inset-0 z-[75] xl:hidden"
        role="dialog"
        aria-modal="true"
        :aria-label="t('shell.filters')"
      >
        <button
          type="button"
          class="absolute inset-0 bg-brand-text/35"
          :aria-label="t('shell.close')"
          @click="closeSheet"
        />

        <div
          class="absolute inset-x-0 bottom-0 flex max-h-[min(85dvh,40rem)] flex-col overflow-hidden rounded-t-[20px] border border-brand-border bg-brand-surface md:inset-inline-end-0 md:inset-inline-start-auto md:w-[min(24rem,100%)] md:rounded-ss-[20px]"
          style="padding-bottom: max(12px, env(safe-area-inset-bottom)); box-shadow: var(--shadow-overlay)"
        >
          <div class="mx-auto mt-2 h-1 w-10 shrink-0 rounded-full bg-brand-border md:hidden" aria-hidden="true" />
          <div class="flex shrink-0 items-center justify-between gap-3 px-4 pb-3 pt-2">
            <div class="min-w-0">
              <p class="text-base font-semibold text-brand-text">{{ t('shell.filters') }}</p>
              <p
                v-if="(activeCount ?? 0) > 0"
                class="text-xs text-brand-text-secondary"
              >
                {{ t('shell.filtersWithCount', { count: activeCount }) }}
              </p>
            </div>
            <button
              type="button"
              class="app-btn-ghost"
              :aria-label="t('shell.close')"
              @click="closeSheet"
            >
              <X class="h-5 w-5" :stroke-width="2" />
            </button>
          </div>

          <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-2">
            <slot name="filters" />
          </div>

          <div class="shrink-0 border-t border-[var(--border-soft)] px-4 py-3">
            <div class="flex flex-col gap-2">
              <button
                type="button"
                class="app-btn-primary w-full"
                @click="applyAndClose"
              >
                {{ resolvedApplyLabel }}
              </button>
              <button
                type="button"
                class="inline-flex h-11 w-full items-center justify-center rounded-[10px] border border-brand-border bg-brand-surface text-sm font-medium text-brand-text transition hover:bg-[var(--surface-subtle)]"
                @click="resetAndClose"
              >
                {{ t('shell.resetFilters') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
