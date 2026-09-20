import { describe, expect, it } from 'vitest'

import {
  applyPullResistance,
  canStartPullFromTarget,
  isEditableTarget,
  isOverlayBlockingPull,
  phaseFromPull,
  PULL_THRESHOLD_PX,
} from './pullToRefresh'

describe('pullToRefresh helpers', () => {
  it('applies resistance and caps the pull distance', () => {
    expect(applyPullResistance(-10)).toBe(0)
    expect(applyPullResistance(0)).toBe(0)
    expect(applyPullResistance(100)).toBeLessThan(100)
    expect(applyPullResistance(1000)).toBe(128)
  })

  it('maps pull distance to phases', () => {
    expect(phaseFromPull(0, false)).toBe('idle')
    expect(phaseFromPull(PULL_THRESHOLD_PX - 1, false)).toBe('pulling')
    expect(phaseFromPull(PULL_THRESHOLD_PX, false)).toBe('ready')
    expect(phaseFromPull(10, true)).toBe('refreshing')
  })

  it('detects editable targets', () => {
    const input = document.createElement('input')
    const wrap = document.createElement('div')
    wrap.appendChild(input)
    document.body.appendChild(wrap)
    expect(isEditableTarget(input)).toBe(true)
    expect(isEditableTarget(wrap)).toBe(false)
    wrap.remove()
  })

  it('blocks when an aria-modal overlay is open', () => {
    expect(isOverlayBlockingPull()).toBe(false)
    const modal = document.createElement('div')
    modal.setAttribute('aria-modal', 'true')
    document.body.appendChild(modal)
    expect(isOverlayBlockingPull()).toBe(true)
    modal.remove()
  })

  it('requires the scroller to be at the top', () => {
    const scroller = document.createElement('div')
    Object.defineProperty(scroller, 'scrollTop', { value: 12, configurable: true })
    expect(canStartPullFromTarget(scroller, scroller)).toBe(false)

    Object.defineProperty(scroller, 'scrollTop', { value: 0, configurable: true })
    const child = document.createElement('div')
    scroller.appendChild(child)
    expect(canStartPullFromTarget(child, scroller)).toBe(true)

    child.setAttribute('data-no-pull-refresh', '')
    expect(canStartPullFromTarget(child, scroller)).toBe(false)
  })
})
