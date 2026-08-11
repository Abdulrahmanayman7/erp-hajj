<script setup lang="ts">
import { CheckCircle2, CircleAlert, Info, X } from 'lucide-vue-next'

import { useToast, type ToastVariant } from '@/shared/composables/useToast'

const { toasts, dismiss } = useToast()

function iconFor(variant: ToastVariant) {
  if (variant === 'error') return CircleAlert
  if (variant === 'info') return Info
  return CheckCircle2
}

function classesFor(variant: ToastVariant): string {
  if (variant === 'error') {
    return 'border-red-200 bg-white text-red-800'
  }
  if (variant === 'info') {
    return 'border-brand-border bg-white text-brand-text'
  }
  return 'border-emerald-200/80 bg-white text-brand-primary-dark'
}
</script>

<template>
  <div
    class="pointer-events-none fixed inset-x-0 top-4 z-[400] flex flex-col items-center gap-2 px-4 sm:top-6"
    aria-live="polite"
    aria-relevant="additions"
  >
    <TransitionGroup
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-xl border px-4 py-3 shadow-[0_16px_40px_-24px_rgba(23,32,29,0.45)]"
        :class="classesFor(toast.variant)"
        role="status"
      >
        <component
          :is="iconFor(toast.variant)"
          class="mt-0.5 h-5 w-5 shrink-0"
          :class="toast.variant === 'error' ? 'text-red-600' : toast.variant === 'info' ? 'text-brand-primary' : 'text-brand-success'"
          :stroke-width="2"
          aria-hidden="true"
        />
        <p class="min-w-0 flex-1 text-sm font-semibold leading-relaxed">
          {{ toast.message }}
        </p>
        <button
          type="button"
          class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-brand-text-muted transition hover:bg-brand-bg hover:text-brand-text"
          :aria-label="'إغلاق'"
          @click="dismiss(toast.id)"
        >
          <X class="h-3.5 w-3.5" :stroke-width="2.25" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
