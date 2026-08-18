import { onMounted, onUnmounted, readonly, ref } from 'vue'

const isFullscreen = ref(false)

function syncFullscreenState(): void {
  isFullscreen.value = Boolean(document.fullscreenElement)
}

async function enterFullscreen(): Promise<void> {
  const root = document.documentElement
  if (!root.requestFullscreen) return
  await root.requestFullscreen()
}

async function exitFullscreen(): Promise<void> {
  if (!document.exitFullscreen || !document.fullscreenElement) return
  await document.exitFullscreen()
}

export function useFullscreen() {
  onMounted(() => {
    syncFullscreenState()
    document.addEventListener('fullscreenchange', syncFullscreenState)
  })

  onUnmounted(() => {
    document.removeEventListener('fullscreenchange', syncFullscreenState)
  })

  async function toggleFullscreen(): Promise<void> {
    try {
      if (document.fullscreenElement) {
        await exitFullscreen()
      } else {
        await enterFullscreen()
      }
    } catch {
      // Browser may deny fullscreen without a gesture or in unsupported contexts.
      syncFullscreenState()
    }
  }

  return {
    isFullscreen: readonly(isFullscreen),
    toggleFullscreen,
  }
}
