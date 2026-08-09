<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref } from 'vue'

const props = withDefaults(
  defineProps<{
    text: string
    side?: 'top' | 'bottom'
  }>(),
  {
    side: 'top',
  },
)

const triggerRef = ref<HTMLElement | null>(null)
const visible = ref(false)
const coords = ref({ top: 0, left: 0 })

function updatePosition(): void {
  const el = triggerRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const gap = 10
  coords.value = {
    top: props.side === 'top' ? rect.top - gap : rect.bottom + gap,
    left: rect.left + rect.width / 2,
  }
}

function bindListeners(): void {
  window.addEventListener('scroll', updatePosition, true)
  window.addEventListener('resize', updatePosition)
}

function unbindListeners(): void {
  window.removeEventListener('scroll', updatePosition, true)
  window.removeEventListener('resize', updatePosition)
}

async function show(): Promise<void> {
  visible.value = true
  await nextTick()
  updatePosition()
  bindListeners()
}

function hide(): void {
  visible.value = false
  unbindListeners()
}

const tipStyle = computed(() => ({
  top: `${coords.value.top}px`,
  left: `${coords.value.left}px`,
}))

onUnmounted(() => {
  hide()
})
</script>

<template>
  <span
    ref="triggerRef"
    class="inline-flex"
    @mouseenter="show"
    @mouseleave="hide"
    @focusin="show"
    @focusout="hide"
  >
    <slot />
  </span>

  <Teleport to="body">
    <span
      v-if="visible && text"
      role="tooltip"
      class="pointer-events-none fixed z-[300] -translate-x-1/2"
      :class="side === 'top' ? '-translate-y-full' : ''"
      :style="tipStyle"
    >
      <span
        class="relative block overflow-hidden rounded-xl border border-white/10 bg-[linear-gradient(180deg,#243530_0%,#1A2622_100%)] px-3 py-2 text-center shadow-[0_12px_28px_-12px_rgba(15,26,22,0.72),0_2px_6px_-2px_rgba(15,26,22,0.35)]"
      >
        <span
          class="pointer-events-none absolute inset-x-3 top-0 h-px bg-gradient-to-l from-transparent via-brand-gold/70 to-transparent"
          aria-hidden="true"
        />
        <span class="relative block text-[12px] font-semibold leading-none tracking-[0.01em] text-white/95">
          {{ text }}
        </span>
      </span>

      <span
        class="absolute start-1/2 h-2 w-2 -translate-x-1/2 rotate-45 bg-[#1A2622] shadow-sm"
        :class="side === 'top' ? 'top-full -mt-1' : 'bottom-full -mb-1'"
        aria-hidden="true"
      />
    </span>
  </Teleport>
</template>
