import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  assignAssetCustody,
  createAsset,
  declareAssetLost,
  deleteAsset,
  restoreAsset,
  retireAsset,
  returnAssetCustody,
  sendAssetToMaintenance,
  updateAsset,
} from '../api/assetsApi'
import { assetDetailQueryKey, assetsQueryKey } from '../queries/useAssetsQuery'
import { assetCustodiesQueryKey, myCustodiesQueryKey } from '../queries/useCustodiesQuery'
import type {
  Asset,
  AssetPayload,
  AssignCustodyPayload,
  ReturnCustodyPayload,
} from '../types/assets'

async function invalidateAssets(
  client: ReturnType<typeof useQueryClient>,
  id?: number,
): Promise<void> {
  await client.invalidateQueries({ queryKey: assetsQueryKey })
  await client.invalidateQueries({ queryKey: assetCustodiesQueryKey })
  await client.invalidateQueries({ queryKey: myCustodiesQueryKey })
  if (id) {
    await client.invalidateQueries({ queryKey: assetDetailQueryKey(id) })
  }
}

export function useCreateAssetMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: createAsset,
    onSuccess: (asset: Asset) => invalidateAssets(client, asset.id),
  })
}

export function useUpdateAssetMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: AssetPayload }) =>
      updateAsset(id, payload),
    onSuccess: (asset: Asset) => invalidateAssets(client, asset.id),
  })
}

export function useDeleteAssetMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteAsset(id),
    onSuccess: (_, id) => invalidateAssets(client, id),
  })
}

export function useSendAssetToMaintenanceMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => sendAssetToMaintenance(id),
    onSuccess: (asset: Asset) => invalidateAssets(client, asset.id),
  })
}

export function useRestoreAssetMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => restoreAsset(id),
    onSuccess: (asset: Asset) => invalidateAssets(client, asset.id),
  })
}

export function useRetireAssetMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => retireAsset(id, reason),
    onSuccess: (asset: Asset) => invalidateAssets(client, asset.id),
  })
}

export function useDeclareAssetLostMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => declareAssetLost(id, reason),
    onSuccess: (asset: Asset) => invalidateAssets(client, asset.id),
  })
}

export function useAssignAssetCustodyMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: AssignCustodyPayload }) =>
      assignAssetCustody(id, payload),
    onSuccess: (asset: Asset) => invalidateAssets(client, asset.id),
  })
}

export function useReturnAssetCustodyMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: ReturnCustodyPayload }) =>
      returnAssetCustody(id, payload),
    onSuccess: (asset: Asset) => invalidateAssets(client, asset.id),
  })
}
