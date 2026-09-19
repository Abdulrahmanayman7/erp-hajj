<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AlertTriangle, Loader2, X } from 'lucide-vue-next'

const props = defineProps<{
  open: boolean
  kind: 'suspend' | 'archive' | null
  reason: string
  formError: string
  fieldError: string
  submitting: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:reason': [value: string]
}>()

const { t } = useI18n()

const title = computed(() =>
  props.kind === 'archive'
    ? t('platform.lifecycle.archiveTitle')
    : t('platform.lifecycle.suspendTitle'),
)
const subtitle = computed(() =>
  props.kind === 'archive'
    ? t('platform.lifecycle.archiveSubtitle')
    : t('platform.lifecycle.suspendSubtitle'),
)
const confirmLabel = computed(() =>
  props.kind === 'archive' ? t('platform.actions.archive') : t('platform.actions.suspend'),
)
const toneClass = computed(() =>
  props.kind === 'archive' ? 'bg-neutral-50' : 'bg-orange-50/70',
)
const confirmClass = computed(() =>
  props.kind === 'archive'
    ? 'bg-neutral-800 hover:bg-neutral-900'
    : 'bg-orange-700 hover:bg-orange-800',
)
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && kind"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      role="presentation"
    >
      <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
      <div
        class="relative z-10 w-full max-w-lg overflow-hidden rounded-2xl bg-brand-surface shadow-xl"
        role="dialog"
        aria-modal="true"
        v-autofocus-when
        @click.stop
      >
        <header
          class="flex items-start justify-between border-b border-brand-border px-4 py-4 sm:px-5"
          :class="toneClass"
        >
          <div>
            <h3 class="flex items-center gap-2 text-lg font-bold text-brand-text">
              <AlertTriangle class="h-5 w-5 text-orange-700" />
              {{ title }}
            </h3>
            <p class="mt-1 text-sm text-brand-text-secondary">{{ subtitle }}</p>
          </div>
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text-muted hover:bg-brand-bg"
            :disabled="submitting"
            :aria-label="t('platform.close')"
            @click="emit('close')"
          >
            <X class="h-4 w-4" />
          </button>
        </header>

        <form class="space-y-4 px-4 py-5 sm:px-5" @submit.prevent="emit('submit')">
          <label class="block">
            <span class="text-sm font-medium">{{ t('platform.fields.reason') }}</span>
            <textarea
              :value="reason"
              required
              rows="4"
              class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
              @input="emit('update:reason', ($event.target as HTMLTextAreaElement).value)"
            />
            <p v-if="fieldError" class="mt-1 text-xs text-red-600">{{ fieldError }}</p>
          </label>

          <p
            v-if="formError"
            class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          >
            {{ formError }}
          </p>

          <div
            class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end"
            style="padding-bottom: max(0px, env(safe-area-inset-bottom))"
          >
            <button
              type="button"
              class="inline-flex h-11 items-center justify-center rounded-xl border px-4 text-sm font-semibold"
              :disabled="submitting"
              @click="emit('close')"
            >
              {{ t('platform.cancel') }}
            </button>
            <button
              type="submit"
              class="inline-flex h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold text-white disabled:opacity-60"
              :class="confirmClass"
              :disabled="submitting"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              {{ confirmLabel }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
