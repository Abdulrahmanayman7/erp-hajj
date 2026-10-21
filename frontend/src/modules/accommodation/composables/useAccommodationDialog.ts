import { nextTick, onUnmounted, toValue, watch, type MaybeRefOrGetter } from 'vue'

/** Keep keyboard interaction inside the active drawer and restore the opener on close. */
export function useAccommodationDialog(open: MaybeRefOrGetter<boolean>, close: () => void) {
  let opener: HTMLElement | null = null
  let previousOverflow = ''
  let locked = false
  const controls = () => Array.from(document.querySelectorAll<HTMLElement>(
    '.accommodation-drawer button:not([disabled]), .accommodation-drawer input:not([disabled]), .accommodation-drawer [tabindex="0"], .accommodation-drawer a[href]',
  )).filter(element => element.getClientRects().length > 0)
  function keydown(event: KeyboardEvent) {
    // AppSelect owns keyboard navigation while its teleported menu is open.
    if (document.querySelector('[role="listbox"]')) return
    if (event.key === 'Escape') { event.preventDefault(); close(); return }
    if (event.key !== 'Tab') return
    const items = controls()
    const first = items[0]
    const last = items.at(-1)
    if (!first || !last) return
    if (event.shiftKey && (document.activeElement === first || !items.includes(document.activeElement as HTMLElement))) {
      event.preventDefault(); last.focus()
    } else if (!event.shiftKey && (document.activeElement === last || !items.includes(document.activeElement as HTMLElement))) {
      event.preventDefault(); first.focus()
    }
  }
  function release() {
    if (!locked) return
    document.body.style.overflow = previousOverflow
    document.removeEventListener('keydown', keydown)
    opener?.focus()
    locked = false
  }
  watch(() => toValue(open), async visible => {
    if (!visible) { release(); return }
    opener = document.activeElement as HTMLElement | null
    previousOverflow = document.body.style.overflow
    locked = true
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', keydown)
    await nextTick()
    if (toValue(open)) controls()[0]?.focus()
  })
  onUnmounted(release)
}
