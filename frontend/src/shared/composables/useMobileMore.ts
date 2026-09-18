import { ref } from 'vue'

const moreOpen = ref(false)

export function useMobileMore(): {
  moreOpen: typeof moreOpen
  openMore: () => void
  closeMore: () => void
} {
  function openMore(): void {
    moreOpen.value = true
  }

  function closeMore(): void {
    moreOpen.value = false
  }

  return { moreOpen, openMore, closeMore }
}
