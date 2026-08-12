/**
 * Safe deep-link resolver for notification entities.
 * Never trusts arbitrary URLs from the database — only maps known entity types.
 */
export function resolveNotificationRoute(n: {
  entity_type?: string | null
  entity_id?: number | null
  type?: string
}): string | null {
  const entityType = n.entity_type?.trim() || null
  if (!entityType) {
    return null
  }

  const id = n.entity_id
  const hasId = id != null && Number.isFinite(id) && id > 0

  switch (entityType) {
    case 'contract':
      return hasId ? `/app/contracts/${id}` : null
    case 'meeting':
      return hasId ? `/app/meetings/${id}` : null
    case 'decision':
      return hasId ? `/app/decisions/${id}` : null
    case 'task':
      return hasId ? `/app/tasks/${id}` : null
    case 'asset':
      return hasId ? `/app/assets/${id}` : null
    case 'inventory_item':
      return hasId ? `/app/inventory/items/${id}` : null
    case 'warehouse':
      return hasId ? `/app/warehouses/${id}` : null
    case 'custody':
      // MVP: custody events without an asset id go to my-custodies
      return '/app/my-custodies'
    default:
      return null
  }
}
