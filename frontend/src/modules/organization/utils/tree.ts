import type { OrganizationUnit } from '../types/organization'

/** Flatten tree preserving depth for selectors. */
export function flattenUnits(
  nodes: OrganizationUnit[],
  depth = 0,
): Array<OrganizationUnit & { depth: number }> {
  const result: Array<OrganizationUnit & { depth: number }> = []
  for (const node of nodes) {
    result.push({ ...node, depth: node.depth ?? depth })
    if (node.children?.length) {
      result.push(...flattenUnits(node.children, depth + 1))
    }
  }
  return result
}

/** Collect id of unit and all descendants. */
export function collectDescendantIds(unit: OrganizationUnit): Set<number> {
  const ids = new Set<number>([unit.id])
  const walk = (node: OrganizationUnit) => {
    for (const child of node.children ?? []) {
      ids.add(child.id)
      walk(child)
    }
  }
  walk(unit)
  return ids
}

export function findUnitInTree(
  nodes: OrganizationUnit[],
  id: number,
): OrganizationUnit | null {
  for (const node of nodes) {
    if (node.id === id) return node
    const found = findUnitInTree(node.children ?? [], id)
    if (found) return found
  }
  return null
}
