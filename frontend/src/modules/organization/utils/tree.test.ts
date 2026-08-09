import { describe, expect, it } from 'vitest'

import type { OrganizationUnit } from '../types/organization'
import { collectDescendantIds, findUnitInTree, flattenUnits } from '../utils/tree'

function unit(partial: Partial<OrganizationUnit> & Pick<OrganizationUnit, 'id' | 'name'>): OrganizationUnit {
  return {
    code: partial.code ?? `C${partial.id}`,
    type: partial.type ?? 'department',
    status: partial.status ?? 'active',
    parent_id: partial.parent_id ?? null,
    sort_order: partial.sort_order ?? 0,
    manager: null,
    children_count: partial.children_count ?? (partial.children?.length ?? 0),
    depth: partial.depth ?? 0,
    children: partial.children,
    created_at: null,
    updated_at: null,
    ...partial,
  }
}

describe('organization tree utils', () => {
  const tree: OrganizationUnit[] = [
    unit({
      id: 1,
      name: 'Root',
      children: [
        unit({
          id: 2,
          name: 'Child',
          parent_id: 1,
          depth: 1,
          children: [unit({ id: 3, name: 'Leaf', parent_id: 2, depth: 2 })],
        }),
      ],
    }),
  ]

  it('flattens nested units', () => {
    expect(flattenUnits(tree).map((u) => u.id)).toEqual([1, 2, 3])
  })

  it('finds a nested unit', () => {
    expect(findUnitInTree(tree, 3)?.name).toBe('Leaf')
    expect(findUnitInTree(tree, 99)).toBeNull()
  })

  it('collects descendant ids including self', () => {
    const ids = collectDescendantIds(tree[0])
    expect([...ids].sort()).toEqual([1, 2, 3])
  })
})
