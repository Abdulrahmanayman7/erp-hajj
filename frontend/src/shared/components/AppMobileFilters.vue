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
    /** When false, hide the mobile search field (filters-only pages). */
    showSearch?: boolean
  }>(),
  {
    search: '',
    searchPlaceholder: '',
    showSearch: true,
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
    <!-- Mobile: search + filters trigger -->
    <div class="flex items-center gap-2 md:hidden">
      <div v-if="showSearch" class="relative min-w-0 flex-1">
        <Search
          class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
          :stroke-width="1.75"
        />
        <input
          :value="search"
          type="search"
          class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
          :placeholder="searchPlaceholder"
          @input="emit('update:search', ($event.target as HTMLInputElement).value)"
        />
      </div>
      <button
        type="button"
        class="inline-flex h-11 shrink-0 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg"
        :class="[
          { 'border-brand-primary/40 bg-brand-primary-soft text-brand-primary-dark': (activeCount ?? 0) > 0 },
          !showSearch ? 'w-full justify-center' : '',
        ]"
        @click="openSheet"
      >
        <Filter class="h-4 w-4" :stroke-width="2" />
        <span>{{ filterLabel }}</span>
      </button>
    </div>

    <!-- Desktop: full toolbar slot -->
    <div class="hidden md:block">
      <slot name="desktop" />
    </div>

    <Teleport to="body">
      <div
        v-if="open"
        class="fixed inset-0 z-[75] md:hidden"
        role="dialog"
        aria-modal="true"
        :aria-label="t('shell.filters')"
      >
        <button
          type="button"
          class="absolute inset-0 bg-brand-text/40"
          :aria-label="t('shell.close')"
          @click="closeSheet"
        />

        <div
          class="absolute inset-x-0 bottom-0 flex max-h-[85dvh] flex-col overflow-hidden rounded-t-[1.5rem] border border-brand-border bg-brand-surface"
          style="padding-bottom: max(12px, env(safe-area-inset-bottom))"
        >
          <div class="flex shrink-0 items-center justify-between gap-3 border-b border-brand-border px-4 py-3">
            <p class="text-base font-bold text-brand-text">{{ t('shell.filters') }}</p>
            <button
              type="button"
              class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-brand-text-secondary transition hover:bg-brand-bg"
              :aria-label="t('shell.close')"
              @click="closeSheet"
            >
              <X class="h-5 w-5" :stroke-width="2" />
            </button>
          </div>

          <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4">
            <slot name="filters" />
          </div>

          <div class="shrink-0 border-t border-brand-border px-4 py-3">
            <div class="flex flex-col gap-2">
              <button
                type="button"
                class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-brand-primary-dark text-sm font-semibold text-white transition hover:bg-brand-primary"
                @click="applyAndClose"
              >
                {{ t('shell.applyFilters') }}
              </button>
              <button
                type="button"
                class="inline-flex h-11 w-full items-center justify-center rounded-xl border border-brand-border bg-brand-surface text-sm font-semibold text-brand-text transition hover:bg-brand-bg"
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
