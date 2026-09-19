const BLOCKING_MENUS = '[data-app-select-menu], [data-app-datetime-menu], [data-app-date-menu], [data-app-phone-menu]'

export function requestSubmitClosestForm(event: KeyboardEvent): boolean {
  if (event.key !== 'Enter' || event.shiftKey || event.altKey || event.metaKey || event.isComposing) {
    return false
  }

  const target = event.target
  if (!(target instanceof HTMLElement)) return false
  if (target instanceof HTMLTextAreaElement) return false
  if (target.closest(BLOCKING_MENUS)) return false

  if (target instanceof HTMLButtonElement || target instanceof HTMLInputElement) {
    const type = target.type
    if (type === 'submit' || type === 'button' || type === 'reset') return false
  }

  const form = target.closest('form')
  if (!(form instanceof HTMLFormElement)) return false
  if (form.querySelector('[data-form-submitting]')) return false

  event.preventDefault()
  form.requestSubmit()
  return true
}

export function requestSubmitFromControl(el: HTMLElement | null): boolean {
  const form = el?.closest('form')
  if (!(form instanceof HTMLFormElement)) return false
  form.requestSubmit()
  return true
}
