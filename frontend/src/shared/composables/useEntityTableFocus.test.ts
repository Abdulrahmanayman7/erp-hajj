import { describe, expect, it, vi } from 'vitest'

import { useEntityTableFocus } from './useEntityTableFocus'

describe('useEntityTableFocus', () => {
  it('tones and accents rows like Decisions', () => {
    const { focusedRowIndex, rowToneClass, rowAccentClass, focusRow } = useEntityTableFocus()
    expect(rowToneClass(0)).toBe('bg-brand-surface')
    expect(rowToneClass(1)).toBe('bg-[#FAFBFA]')
    expect(rowAccentClass(0)).toBe('border-s-transparent')

    focusRow(1, 3)
    expect(focusedRowIndex.value).toBe(1)
    expect(rowToneClass(1)).toBe('bg-[#EDF6F1]')
    expect(rowAccentClass(1)).toBe('border-s-brand-primary')
  })

  it('handles keyboard navigation and activate', () => {
    const onActivate = vi.fn()
    const { focusedRowIndex, onTableKeydown } = useEntityTableFocus({ onActivate })

    onTableKeydown(new KeyboardEvent('keydown', { key: 'ArrowDown' }), 4)
    expect(focusedRowIndex.value).toBe(0)
    onTableKeydown(new KeyboardEvent('keydown', { key: 'ArrowDown' }), 4)
    expect(focusedRowIndex.value).toBe(1)
    onTableKeydown(new KeyboardEvent('keydown', { key: 'Enter' }), 4)
    expect(onActivate).toHaveBeenCalledWith(1)
    onTableKeydown(new KeyboardEvent('keydown', { key: 'Home' }), 4)
    expect(focusedRowIndex.value).toBe(0)
    onTableKeydown(new KeyboardEvent('keydown', { key: 'End' }), 4)
    expect(focusedRowIndex.value).toBe(3)
  })
})
