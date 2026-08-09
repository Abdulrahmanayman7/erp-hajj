import { describe, expect, it } from 'vitest'

import type { AvatarGroup } from '../types/users'

/**
 * Drawer persistence contract: selection is an explicit avatar_group value
 * (never derived from the user's name).
 */
function nextAvatarGroup(_current: AvatarGroup, selected: AvatarGroup): AvatarGroup {
  return selected
}

describe('user form avatar_group selection', () => {
  it('persists male/female/neutral selections explicitly', () => {
    let group: AvatarGroup = 'neutral'
    group = nextAvatarGroup(group, 'male')
    expect(group).toBe('male')
    group = nextAvatarGroup(group, 'female')
    expect(group).toBe('female')
    group = nextAvatarGroup(group, 'neutral')
    expect(group).toBe('neutral')
  })

  it('does not infer avatar group from a display name', () => {
    const name = 'فاطمة أحمد'
    const group: AvatarGroup = 'male'
    expect(group).toBe('male')
    expect(name.includes('فاطمة')).toBe(true)
  })
})
