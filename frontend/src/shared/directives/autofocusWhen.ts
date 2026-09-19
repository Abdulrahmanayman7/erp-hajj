import { nextTick, type Directive } from 'vue'

const FIELD_SELECTOR =
  'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([readonly]):not([disabled]), textarea:not([readonly]):not([disabled])'

export function focusFirstFormField(root: HTMLElement): void {
  const preferred = root.querySelector<HTMLElement>('[data-autofocus]')
  const fallback = root.querySelector<HTMLElement>(FIELD_SELECTOR)
  const target = preferred ?? fallback
  target?.focus()
}

function isActive(value: unknown): boolean {
  return value !== false && value !== 0 && value !== 'false'
}

export const autofocusWhen: Directive<HTMLElement, boolean | string | number | undefined> = {
  mounted(el, binding) {
    if (!isActive(binding.value)) return
    void nextTick(() => focusFirstFormField(el))
  },
  updated(el, binding) {
    if (!isActive(binding.value) || isActive(binding.oldValue)) return
    void nextTick(() => focusFirstFormField(el))
  },
}
