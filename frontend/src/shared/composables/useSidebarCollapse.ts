import { readonly, ref, watch } from 'vue'

const COLLAPSED_KEY = 'erp-hajj.sidebar.collapsed'

const collapsed = ref(false)
let hydrated = false

function hydrateFromStorage(): void {
  if (hydrated || typeof localStorage === 'undefined') {
    return
  }

  collapsed.value = localStorage.getItem(COLLAPSED_KEY) === '1'
  hydrated = true
}

watch(collapsed, (value) => {
  if (typeof localStorage === 'undefined') {
    return
  }

  localStorage.setItem(COLLAPSED_KEY, value ? '1' : '0')
})

export function useSidebarCollapse() {
  hydrateFromStorage()

  function toggleCollapsed(): void {
    collapsed.value = !collapsed.value
  }

  function setCollapsed(value: boolean): void {
    collapsed.value = value
  }

  return {
    collapsed: readonly(collapsed),
    toggleCollapsed,
    setCollapsed,
  }
}
