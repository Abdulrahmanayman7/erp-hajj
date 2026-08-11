import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  activateWarehouse,
  createWarehouse,
  deactivateWarehouse,
  deleteWarehouse,
  updateWarehouse,
} from '../api/warehousesApi'
import { warehouseDetailQueryKey, warehousesQueryKey } from '../queries/useWarehousesQuery'
import type { Warehouse, WarehousePayload } from '../types/warehouses'

async function invalidateWarehouses(
  client: ReturnType<typeof useQueryClient>,
  id?: number,
): Promise<void> {
  await client.invalidateQueries({ queryKey: warehousesQueryKey })
  if (id) {
    await client.invalidateQueries({ queryKey: warehouseDetailQueryKey(id) })
  }
}

export function useCreateWarehouseMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: createWarehouse,
    onSuccess: (warehouse: Warehouse) => invalidateWarehouses(client, warehouse.id),
  })
}

export function useUpdateWarehouseMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: WarehousePayload }) =>
      updateWarehouse(id, payload),
    onSuccess: (warehouse: Warehouse) => invalidateWarehouses(client, warehouse.id),
  })
}

export function useActivateWarehouseMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => activateWarehouse(id),
    onSuccess: (warehouse: Warehouse) => invalidateWarehouses(client, warehouse.id),
  })
}

export function useDeactivateWarehouseMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deactivateWarehouse(id),
    onSuccess: (warehouse: Warehouse) => invalidateWarehouses(client, warehouse.id),
  })
}

export function useDeleteWarehouseMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteWarehouse(id),
    onSuccess: (_, id) => invalidateWarehouses(client, id),
  })
}
