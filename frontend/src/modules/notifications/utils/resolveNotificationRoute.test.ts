import { describe, expect, it } from 'vitest'

import { resolveNotificationRoute } from './resolveNotificationRoute'

describe('resolveNotificationRoute', () => {
  it('maps known entity types with ids', () => {
    expect(resolveNotificationRoute({ entity_type: 'contract', entity_id: 3 })).toBe(
      '/app/contracts/3',
    )
    expect(resolveNotificationRoute({ entity_type: 'meeting', entity_id: 7 })).toBe(
      '/app/meetings/7',
    )
    expect(resolveNotificationRoute({ entity_type: 'decision', entity_id: 2 })).toBe(
      '/app/decisions/2',
    )
    expect(resolveNotificationRoute({ entity_type: 'task', entity_id: 11 })).toBe('/app/tasks/11')
    expect(resolveNotificationRoute({ entity_type: 'asset', entity_id: 5 })).toBe('/app/assets/5')
    expect(resolveNotificationRoute({ entity_type: 'inventory_item', entity_id: 9 })).toBe(
      '/app/inventory/items/9',
    )
    expect(resolveNotificationRoute({ entity_type: 'warehouse', entity_id: 4 })).toBe(
      '/app/warehouses/4',
    )
  })

  it('routes custody to my-custodies without requiring an id', () => {
    expect(resolveNotificationRoute({ entity_type: 'custody', entity_id: 1 })).toBe(
      '/app/my-custodies',
    )
    expect(resolveNotificationRoute({ entity_type: 'custody', entity_id: null })).toBe(
      '/app/my-custodies',
    )
  })

  it('returns null for missing ids on entity-bound routes', () => {
    expect(resolveNotificationRoute({ entity_type: 'task', entity_id: null })).toBeNull()
    expect(resolveNotificationRoute({ entity_type: 'contract', entity_id: 0 })).toBeNull()
    expect(resolveNotificationRoute({ entity_type: 'meeting' })).toBeNull()
  })

  it('returns null for unknown entity types and never trusts arbitrary urls', () => {
    expect(
      resolveNotificationRoute({
        entity_type: 'https://evil.example/phish',
        entity_id: 1,
      }),
    ).toBeNull()
    expect(
      resolveNotificationRoute({
        entity_type: 'employee',
        entity_id: 1,
      }),
    ).toBeNull()
    expect(resolveNotificationRoute({ entity_type: null, entity_id: 1 })).toBeNull()
    expect(resolveNotificationRoute({ entity_type: '', entity_id: 1 })).toBeNull()
    expect(resolveNotificationRoute({})).toBeNull()
  })
})
