import { ref, type Ref } from 'vue'

/**
 * Shared list-table row focus helpers matching Decisions / Meetings / Contracts.
 */
export function useEntityTableFocus(options?: {
  onActivate?: (index: number) => void
}) {
  const focusedRowIndex = ref(-1)

  function rowToneClass(index: number): string {
    if (focusedRowIndex.value === index) {
      return 'bg-[#EDF6F1]'
    }
    return index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'
  }

  function rowAccentClass(index: number): string {
    return focusedRowIndex.value === index ? 'border-s-brand-primary' : 'border-s-transparent'
  }

  function focusRow(index: number, rowCount: number): void {
    if (rowCount <= 0) {
      focusedRowIndex.value = -1
      return
    }
    focusedRowIndex.value = Math.min(Math.max(index, 0), rowCount - 1)
  }

  function onTableKeydown(event: KeyboardEvent, rowCount: number): void {
    if (rowCount <= 0) return
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      focusRow(focusedRowIndex.value < 0 ? 0 : focusedRowIndex.value + 1, rowCount)
      return
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault()
      focusRow(focusedRowIndex.value < 0 ? 0 : focusedRowIndex.value - 1, rowCount)
      return
    }
    if (event.key === 'Home') {
      event.preventDefault()
      focusRow(0, rowCount)
      return
    }
    if (event.key === 'End') {
      event.preventDefault()
      focusRow(rowCount - 1, rowCount)
      return
    }
    if (event.key === 'Enter' && focusedRowIndex.value >= 0) {
      event.preventDefault()
      options?.onActivate?.(focusedRowIndex.value)
    }
  }

  function syncFocusedRow(rowCount: number): void {
    if (rowCount <= 0) {
      focusedRowIndex.value = -1
      return
    }
    if (focusedRowIndex.value >= rowCount) {
      focusedRowIndex.value = rowCount - 1
    }
  }

  return {
    focusedRowIndex: focusedRowIndex as Ref<number>,
    rowToneClass,
    rowAccentClass,
    focusRow,
    onTableKeydown,
    syncFocusedRow,
  }
}
