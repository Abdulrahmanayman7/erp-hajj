import { onMounted, onUnmounted, readonly, ref, type Ref } from 'vue'

import { isStandaloneDisplay } from './displayMode'

export type BeforeInstallPromptEventLike = Event & {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>
}

export type PwaInstallPlatform = 'chromium' | 'ios' | 'unsupported'

export interface UsePwaInstallResult {
  canInstall: Ref<boolean>
  isInstalled: Ref<boolean>
  platform: Ref<PwaInstallPlatform>
  showIosHint: Ref<boolean>
  promptInstall: () => Promise<'accepted' | 'dismissed' | 'unavailable'>
  dismissIosHint: () => void
}

const IOS_HINT_DISMISSED_KEY = 'erp-hajj.pwa.iosHintDismissed'

function detectIosSafari(): boolean {
  if (typeof navigator === 'undefined') {
    return false
  }
  const ua = navigator.userAgent
  const isIos =
    /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
  const isWebkit = /WebKit/.test(ua) && !/CriOS|FxiOS|EdgiOS/.test(ua)
  return isIos && isWebkit
}

function readIosHintDismissed(): boolean {
  try {
    return localStorage.getItem(IOS_HINT_DISMISSED_KEY) === '1'
  } catch {
    return false
  }
}

/**
 * Lightweight install capability for Chromium `beforeinstallprompt` + iOS A2HS hint.
 * Does not force prompts on visit; caller decides when to surface UI.
 */
export function usePwaInstall(): UsePwaInstallResult {
  const deferred = ref<BeforeInstallPromptEventLike | null>(null)
  const canInstall = ref(false)
  const isInstalled = ref(isStandaloneDisplay())
  const platform = ref<PwaInstallPlatform>('unsupported')
  const showIosHint = ref(false)

  let media: MediaQueryList | null = null

  function refreshPlatform(): void {
    if (isStandaloneDisplay()) {
      isInstalled.value = true
      canInstall.value = false
      showIosHint.value = false
      platform.value = 'chromium'
      return
    }

    if (detectIosSafari()) {
      platform.value = 'ios'
      canInstall.value = false
      showIosHint.value = !readIosHintDismissed()
      return
    }

    platform.value = deferred.value ? 'chromium' : 'unsupported'
    canInstall.value = deferred.value !== null
    showIosHint.value = false
  }

  function onBeforeInstallPrompt(event: Event): void {
    event.preventDefault()
    deferred.value = event as BeforeInstallPromptEventLike
    refreshPlatform()
  }

  function onAppInstalled(): void {
    deferred.value = null
    isInstalled.value = true
    canInstall.value = false
    showIosHint.value = false
  }

  function onDisplayModeChange(): void {
    refreshPlatform()
  }

  async function promptInstall(): Promise<'accepted' | 'dismissed' | 'unavailable'> {
    const event = deferred.value
    if (!event) {
      return 'unavailable'
    }

    await event.prompt()
    const choice = await event.userChoice
    deferred.value = null
    canInstall.value = false
    if (choice.outcome === 'accepted') {
      isInstalled.value = true
    }
    return choice.outcome
  }

  function dismissIosHint(): void {
    showIosHint.value = false
    try {
      localStorage.setItem(IOS_HINT_DISMISSED_KEY, '1')
    } catch {
      // ignore
    }
  }

  onMounted(() => {
    refreshPlatform()
    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt)
    window.addEventListener('appinstalled', onAppInstalled)

    try {
      media = window.matchMedia('(display-mode: standalone)')
      media.addEventListener('change', onDisplayModeChange)
    } catch {
      media = null
    }
  })

  onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt)
    window.removeEventListener('appinstalled', onAppInstalled)
    media?.removeEventListener('change', onDisplayModeChange)
  })

  return {
    canInstall: readonly(canInstall),
    isInstalled: readonly(isInstalled),
    platform: readonly(platform),
    showIosHint: readonly(showIosHint),
    promptInstall,
    dismissIosHint,
  }
}
