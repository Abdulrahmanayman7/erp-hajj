export type AvatarGroup = 'male' | 'female' | 'neutral'

export const MALE_AVATAR_COUNT = 6 as const
export const FEMALE_AVATAR_COUNT = 6 as const

export interface AvatarUserRef {
  id: number
  name?: string | null
  avatar_group?: AvatarGroup | string | null
}

function normalizeAvatarGroup(group: string | null | undefined): AvatarGroup {
  if (group === 'male' || group === 'female' || group === 'neutral') {
    return group
  }
  return 'neutral'
}

/**
 * Deterministic index in 1..count based on user.id.
 */
export function getUserAvatarIndex(userId: number, count: number): number {
  const safeCount = count > 0 ? count : 1
  const safeId =
    Number.isFinite(userId) && userId > 0 ? Math.trunc(userId) : 1

  return ((safeId - 1) % safeCount) + 1
}

/**
 * Public URL for a local avatar asset.
 * Group comes only from stored avatar_group — never inferred from the name.
 */
export function getUserAvatar(user: AvatarUserRef | null | undefined): string {
  const group = normalizeAvatarGroup(user?.avatar_group)
  const id = user?.id ?? 0

  if (group === 'male') {
    const index = getUserAvatarIndex(id, MALE_AVATAR_COUNT)
    return `/avatars/male/male-${String(index).padStart(2, '0')}.png`
  }

  if (group === 'female') {
    const index = getUserAvatarIndex(id, FEMALE_AVATAR_COUNT)
    return `/avatars/female/female-${String(index).padStart(2, '0')}.png`
  }

  return '/avatars/neutral/neutral-01.png'
}

export function getUserInitials(name: string | null | undefined): string {
  const parts = (name ?? '').trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0]!.slice(0, 2)
  return `${parts[0]!.slice(0, 1)}${parts[1]!.slice(0, 1)}`
}
