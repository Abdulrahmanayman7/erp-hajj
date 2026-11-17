import { describe, expect, it } from 'vitest'
import { nextTick, ref } from 'vue'

import { useCaptureOnOpen, useFormDirty } from './useFormDirty'

describe('useFormDirty', () => {
  it('is clean until capture, then dirty after mutation', () => {
    const form = ref({ name: 'a', notes: '' })
    const { dirty, capture } = useFormDirty(() => form.value)

    expect(dirty.value).toBe(false)
    capture()
    expect(dirty.value).toBe(false)

    form.value = { ...form.value, name: 'b' }
    expect(dirty.value).toBe(true)

    form.value = { name: 'a', notes: '' }
    expect(dirty.value).toBe(false)
  })

  it('treats File identity as part of dirty snapshot', () => {
    const fileA = new File(['x'], 'a.pdf', { type: 'application/pdf' })
    const fileB = new File(['y'], 'b.pdf', { type: 'application/pdf' })
    const form = ref<{ file: File | null }>({ file: fileA })
    const { dirty, capture } = useFormDirty(() => form.value)

    capture()
    expect(dirty.value).toBe(false)
    form.value = { file: fileB }
    expect(dirty.value).toBe(true)
  })

  it('useCaptureOnOpen captures after open becomes true', async () => {
    const open = ref(false)
    const form = ref({ name: '' })
    const { dirty, capture } = useFormDirty(() => form.value)
    useCaptureOnOpen(open, capture)

    open.value = true
    form.value = { name: 'seeded' }
    await Promise.resolve()
    await nextTick()
    await Promise.resolve()

    expect(dirty.value).toBe(false)
    form.value = { name: 'changed' }
    expect(dirty.value).toBe(true)
  })
})
