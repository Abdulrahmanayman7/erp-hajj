import { apiPost } from '@/shared/api/http'

import type { InventoryMovement } from '../types/movements'
import type {
  AdjustStockPayload,
  IssueStockPayload,
  ReceiveStockPayload,
  ReturnStockPayload,
  TransferStockPayload,
  TransferStockResult,
} from '../types/stock'

export async function receiveStock(payload: ReceiveStockPayload): Promise<InventoryMovement> {
  const response = await apiPost<InventoryMovement>('/api/v1/inventory/receipts', payload)
  return response.data
}

export async function issueStock(payload: IssueStockPayload): Promise<InventoryMovement> {
  const response = await apiPost<InventoryMovement>('/api/v1/inventory/issues', payload)
  return response.data
}

export async function returnStock(payload: ReturnStockPayload): Promise<InventoryMovement> {
  const response = await apiPost<InventoryMovement>('/api/v1/inventory/returns', payload)
  return response.data
}

export async function transferStock(payload: TransferStockPayload): Promise<TransferStockResult> {
  const response = await apiPost<TransferStockResult>('/api/v1/inventory/transfers', payload)
  return response.data
}

export async function adjustStock(payload: AdjustStockPayload): Promise<InventoryMovement> {
  const response = await apiPost<InventoryMovement>('/api/v1/inventory/adjustments', payload)
  return response.data
}
