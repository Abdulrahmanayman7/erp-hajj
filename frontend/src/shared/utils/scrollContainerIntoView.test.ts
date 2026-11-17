import { describe, expect, it } from 'vitest'

import { computeScrollTopToReveal } from './scrollContainerIntoView'

function fakeRect(top: number, bottom: number): DOMRect {
  return {
    top,
    bottom,
    left: 0,
    right: 100,
    width: 100,
    height: bottom - top,
    x: 0,
    y: top,
    toJSON: () => ({}),
  }
}

describe('computeScrollTopToReveal', () => {
  it('scrolls up when the target is above the visible area', () => {
    const container = {
      scrollTop: 120,
      getBoundingClientRect: () => fakeRect(0, 200),
    } as HTMLElement
    const target = {
      getBoundingClientRect: () => fakeRect(-40, 20),
    } as HTMLElement

    expect(computeScrollTopToReveal(container, target, 12)).toBe(68)
  })

  it('scrolls down when the target extends below the visible area', () => {
    const container = {
      scrollTop: 0,
      getBoundingClientRect: () => fakeRect(0, 200),
    } as HTMLElement
    const target = {
      getBoundingClientRect: () => fakeRect(160, 260),
    } as HTMLElement

    expect(computeScrollTopToReveal(container, target, 12)).toBe(72)
  })

  it('keeps scrollTop when the target is already fully visible', () => {
    const container = {
      scrollTop: 40,
      getBoundingClientRect: () => fakeRect(0, 200),
    } as HTMLElement
    const target = {
      getBoundingClientRect: () => fakeRect(40, 120),
    } as HTMLElement

    expect(computeScrollTopToReveal(container, target, 12)).toBe(40)
  })
})
