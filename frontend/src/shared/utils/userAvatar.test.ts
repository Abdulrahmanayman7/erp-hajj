import { describe, expect, it } from 'vitest'

import {
  FEMALE_AVATAR_COUNT,
  MALE_AVATAR_COUNT,
  getUserAvatar,
  getUserAvatarIndex,
  getUserInitials,
} from './userAvatar'

describe('userAvatar helpers', () => {
  it('returns male avatar paths for male group', () => {
    expect(getUserAvatar({ id: 1, avatar_group: 'male' })).toBe('/avatars/male/male-01.png')
    expect(getUserAvatar({ id: 6, avatar_group: 'male' })).toBe('/avatars/male/male-06.png')
    expect(getUserAvatar({ id: 7, avatar_group: 'male' })).toBe('/avatars/male/male-01.png')
  })

  it('returns female avatar paths for female group', () => {
    expect(getUserAvatar({ id: 2, avatar_group: 'female' })).toBe(
      '/avatars/female/female-02.png',
    )
    expect(getUserAvatar({ id: 8, avatar_group: 'female' })).toBe(
      '/avatars/female/female-02.png',
    )
  })

  it('returns neutral avatar for neutral/missing/invalid groups', () => {
    expect(getUserAvatar({ id: 3, avatar_group: 'neutral' })).toBe(
      '/avatars/neutral/neutral-01.png',
    )
    expect(getUserAvatar({ id: 3 })).toBe('/avatars/neutral/neutral-01.png')
    expect(getUserAvatar({ id: 3, avatar_group: 'other' })).toBe(
      '/avatars/neutral/neutral-01.png',
    )
    expect(getUserAvatar(null)).toBe('/avatars/neutral/neutral-01.png')
  })

  it('is deterministic for the same user id and group', () => {
    const first = getUserAvatar({ id: 11, name: 'A', avatar_group: 'male' })
    const second = getUserAvatar({ id: 11, name: 'B', avatar_group: 'male' })
    expect(first).toBe(second)
  })

  it('does not choose avatar from the user name', () => {
    const maleNamed = getUserAvatar({ id: 4, name: 'فاطمة', avatar_group: 'male' })
    const femaleNamed = getUserAvatar({ id: 4, name: 'أحمد', avatar_group: 'female' })
    expect(maleNamed).toContain('/male/')
    expect(femaleNamed).toContain('/female/')
  })

  it('distributes sequential ids across the male set', () => {
    const paths = Array.from({ length: MALE_AVATAR_COUNT }, (_, i) =>
      getUserAvatar({ id: i + 1, avatar_group: 'male' }),
    )
    expect(new Set(paths).size).toBe(MALE_AVATAR_COUNT)
  })

  it('distributes sequential ids across the female set', () => {
    const paths = Array.from({ length: FEMALE_AVATAR_COUNT }, (_, i) =>
      getUserAvatar({ id: i + 1, avatar_group: 'female' }),
    )
    expect(new Set(paths).size).toBe(FEMALE_AVATAR_COUNT)
  })

  it('builds safe avatar index bounds', () => {
    expect(getUserAvatarIndex(1, 6)).toBe(1)
    expect(getUserAvatarIndex(0, 6)).toBe(1)
    expect(getUserAvatarIndex(-2, 6)).toBe(1)
  })

  it('builds initials from the user name', () => {
    expect(getUserInitials('أحمد علي')).toBe('أع')
    expect(getUserInitials('سارة')).toBe('سا')
    expect(getUserInitials('')).toBe('?')
  })
})
