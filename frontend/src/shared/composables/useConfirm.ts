import { readonly, ref } from 'vue'

export type ConfirmVariant = 'danger' | 'primary' | 'warning'

export interface ConfirmOptions {
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: ConfirmVariant
}

interface ConfirmState extends ConfirmOptions {
  open: boolean
  resolve: ((value: boolean) => void) | null
}

const state = ref<ConfirmState>({
  open: false,
  title: '',
  message: '',
  confirmLabel: undefined,
  cancelLabel: undefined,
  variant: 'warning',
  resolve: null,
})

function close(result: boolean): void {
  const resolve = state.value.resolve
  state.value = {
    ...state.value,
    open: false,
    resolve: null,
  }
  resolve?.(result)
}

function confirm(options: ConfirmOptions): Promise<boolean> {
  if (state.value.open && state.value.resolve) {
    state.value.resolve(false)
  }

  return new Promise<boolean>((resolve) => {
    state.value = {
      open: true,
      title: options.title,
      message: options.message,
      confirmLabel: options.confirmLabel,
      cancelLabel: options.cancelLabel,
      variant: options.variant ?? 'warning',
      resolve,
    }
  })
}

export function useConfirm() {
  return {
    state: readonly(state),
    confirm,
    accept: () => close(true),
    cancel: () => close(false),
  }
}
