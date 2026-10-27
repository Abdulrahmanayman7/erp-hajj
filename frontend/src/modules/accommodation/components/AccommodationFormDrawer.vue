<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

withDefaults(defineProps<{
  open: boolean
  submitting?: boolean
  disabled?: boolean
}>(), { submitting: false, disabled: false })
const emit = defineEmits<{ close: []; submit: [] }>()
const { t } = useI18n()
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50" role="presentation">
      <div class="absolute inset-0 bg-black/30" @click="!submitting && emit('close')" />
      <aside class="accommodation-drawer accommodation-form-drawer app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[540px] flex-col bg-brand-surface shadow-xl" role="dialog" aria-modal="true" :aria-label="t('accommodation.experience.eyebrow')" @click.stop>
        <header class="flex shrink-0 items-start justify-between gap-3 border-b border-brand-border px-4 py-5 sm:px-6">
          <div class="min-w-0">
            <h3 class="text-lg font-bold text-brand-text"><slot name="title" /></h3>
            <p v-if="$slots.subtitle" class="mt-1 text-sm text-brand-text-secondary"><slot name="subtitle" /></p>
          </div>
          <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-brand-text-muted hover:bg-brand-bg" :disabled="submitting" :aria-label="t('accommodation.cancel')" @click="emit('close')"><X class="h-4 w-4" /></button>
        </header>
        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="!submitting && !disabled && emit('submit')">
          <fieldset :disabled="submitting" class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-6 sm:px-6"><slot /></fieldset>
          <footer class="flex shrink-0 flex-col-reverse gap-2 border-t border-brand-border px-4 py-3 sm:flex-row sm:justify-end" style="padding-bottom: max(12px, env(safe-area-inset-bottom))">
            <button type="button" class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold" :disabled="submitting" @click="emit('close')">{{ t('accommodation.cancel') }}</button>
            <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50" :disabled="submitting || disabled"><Loader2 v-if="submitting" class="h-4 w-4 animate-spin" /><slot name="submit-label">{{ t('accommodation.save') }}</slot></button>
          </footer>
        </form>
      </aside>
    </div>
  </Teleport>
</template>

<style>
.accommodation-form-drawer header { background: var(--color-brand-surface); }
.accommodation-form-drawer label > span:first-child { color: var(--color-brand-text); font-size: .875rem; font-weight: 500; }
.accommodation-form-drawer input:not([type="checkbox"]):not([type="radio"]), .accommodation-form-drawer textarea { width: 100%; margin-top: .25rem; min-height: 2.75rem; border: 1px solid var(--color-brand-border); border-radius: .75rem; padding: .5rem .75rem; font-size: .875rem; }
.accommodation-form-drawer input[readonly] { background: var(--color-brand-bg); color: var(--color-brand-text-muted); }
.accommodation-form-drawer input:focus-visible, .accommodation-form-drawer textarea:focus-visible { outline: 2px solid var(--color-brand-primary); outline-offset: 2px; }
.accommodation-form-drawer input[type="checkbox"] { width: 1rem; height: 1rem; accent-color: var(--color-brand-primary); }
</style>
