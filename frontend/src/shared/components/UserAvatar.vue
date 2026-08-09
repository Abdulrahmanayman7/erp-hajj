<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import {
  getUserAvatar,
  getUserInitials,
  type AvatarUserRef,
} from '@/shared/utils/userAvatar'

const props = withDefaults(
  defineProps<{
    user: AvatarUserRef | null | undefined
    size?: 'sm' | 'md' | 'lg'
    alt?: string
    /** When true (default), hide from assistive tech if name is shown nearby. */
    decorative?: boolean
    lazy?: boolean
  }>(),
  {
    size: 'md',
    decorative: true,
    lazy: true,
  },
)

const imageFailed = ref(false)

const pixelSize = computed(() => {
  if (props.size === 'sm') return 36
  if (props.size === 'lg') return 40
  return 40
})

const src = computed(() => getUserAvatar(props.user))
const initials = computed(() => getUserInitials(props.user?.name))
const altText = computed(() => {
  if (props.decorative) return ''
  if (props.alt) return props.alt
  const name = props.user?.name?.trim()
  return name ? `صورة المستخدم ${name}` : 'صورة المستخدم'
})

watch(
  () => [props.user?.id, props.user?.avatar_group, src.value] as const,
  () => {
    imageFailed.value = false
  },
)

function onError(): void {
  imageFailed.value = true
}
</script>

<template>
  <span
    class="relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full border border-brand-border bg-[#F4F6F5] text-brand-primary-dark"
    :style="{ width: `${pixelSize}px`, height: `${pixelSize}px` }"
    :aria-hidden="decorative ? true : undefined"
  >
    <img
      v-if="!imageFailed"
      :src="src"
      :alt="altText"
      class="h-full w-full object-cover object-center"
      :loading="lazy ? 'lazy' : 'eager'"
      decoding="async"
      @error="onError"
    />
    <span
      v-else
      class="flex h-full w-full items-center justify-center bg-brand-primary-soft text-xs font-bold"
      :class="size === 'sm' ? 'text-[11px]' : 'text-xs'"
    >
      {{ initials }}
    </span>
  </span>
</template>
