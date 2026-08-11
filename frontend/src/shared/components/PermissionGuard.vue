<script setup lang="ts">
import { computed } from 'vue'

import { usePermissions } from '@/shared/composables/usePermissions'

const props = defineProps<{
  permission?: string
  any?: string[]
  all?: string[]
}>()

const { can, canAny, canAll } = usePermissions()

const allowed = computed(() => {
  if (props.permission) {
    return can(props.permission)
  }
  if (props.any?.length) {
    return canAny(props.any)
  }
  if (props.all?.length) {
    return canAll(props.all)
  }
  return true
})
</script>

<template>
  <slot v-if="allowed" />
</template>
