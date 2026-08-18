import type { StockState } from '../types/balances'
import type { InventoryCategoryFormState } from '../types/categories'
import type { InventoryItemFormState } from '../types/items'
import type { MovementDirection, MovementType } from '../types/movements'
import type { StockActionFormState, StockActionKind } from '../types/stock'
import type { WarehouseFormState } from '../types/warehouses'

export type InventoryListState = 'loading' | 'error' | 'empty' | 'ready'

export function resolveInventoryListState(input: {
  isLoading: boolean
  isError: boolean
  count: number
}): InventoryListState {
  if (input.isLoading) return 'loading'
  if (input.isError) return 'error'
  if (input.count === 0) return 'empty'
  return 'ready'
}

export function stockStateBadgeClass(state: StockState): string {
  switch (state) {
    case 'low':
      return 'bg-amber-100 text-brand-text ring-1 ring-inset ring-amber-300/80'
    case 'out_of_stock':
      return 'bg-red-100 text-brand-text ring-1 ring-inset ring-red-300/80'
    case 'normal':
    default:
      return 'bg-emerald-100 text-brand-text ring-1 ring-inset ring-emerald-300/80'
  }
}

export function stockStateDotClass(state: StockState): string {
  switch (state) {
    case 'low':
      return 'bg-amber-500'
    case 'out_of_stock':
      return 'bg-red-500'
    case 'normal':
    default:
      return 'bg-emerald-500'
  }
}

export function movementTypeBadgeClass(type: MovementType): string {
  switch (type) {
    case 'opening':
    case 'receipt':
    case 'return':
    case 'transfer_in':
      return 'bg-emerald-100 text-brand-text ring-1 ring-inset ring-emerald-300/80'
    case 'issue':
    case 'transfer_out':
      return 'bg-amber-100 text-brand-text ring-1 ring-inset ring-amber-300/80'
    case 'adjustment':
      return 'bg-violet-100 text-brand-text ring-1 ring-inset ring-violet-300/80'
    default:
      return 'bg-neutral-100 text-brand-text ring-1 ring-inset ring-neutral-300/80'
  }
}

export function formatSignedQuantity(
  quantity: string | number,
  direction: MovementDirection,
): string {
  const value = String(quantity)
  return direction === 'out' ? `−${value}` : `+${value}`
}

export function formatQuantity(value: string | number | null | undefined): string {
  if (value == null || value === '') return '—'
  return String(value)
}

export interface WarehouseFieldErrors {
  name?: string
}

export function validateWarehouseForm(form: WarehouseFormState): WarehouseFieldErrors {
  const errors: WarehouseFieldErrors = {}
  if (!form.name.trim()) {
    errors.name = 'required'
  }
  return errors
}

export interface InventoryItemFieldErrors {
  name?: string
  unit?: string
  minimum_stock?: string
}

export function validateInventoryItemForm(form: InventoryItemFormState): InventoryItemFieldErrors {
  const errors: InventoryItemFieldErrors = {}
  if (!form.name.trim()) {
    errors.name = 'required'
  }
  if (!form.unit) {
    errors.unit = 'required'
  }
  if (form.minimum_stock.trim() !== '') {
    const min = Number(form.minimum_stock)
    if (!Number.isFinite(min) || min < 0) {
      errors.minimum_stock = 'invalid'
    }
  }
  return errors
}

export interface CategoryFieldErrors {
  name?: string
}

export function validateInventoryCategoryForm(
  form: InventoryCategoryFormState,
): CategoryFieldErrors {
  const errors: CategoryFieldErrors = {}
  if (!form.name.trim()) {
    errors.name = 'required'
  }
  return errors
}

export interface StockActionFieldErrors {
  warehouse_id?: string
  inventory_item_id?: string
  quantity?: string
  reason?: string
  source_warehouse_id?: string
  destination_warehouse_id?: string
  direction?: string
}

export function isPositiveQuantity(value: string): boolean {
  const trimmed = value.trim()
  if (!trimmed) return false
  const num = Number(trimmed)
  return Number.isFinite(num) && num > 0
}

export function validateStockActionForm(
  kind: StockActionKind,
  form: StockActionFormState,
): StockActionFieldErrors {
  const errors: StockActionFieldErrors = {}

  if (!isPositiveQuantity(form.quantity)) {
    errors.quantity = 'positive'
  }

  if (!form.reason.trim()) {
    errors.reason = 'required'
  }

  if (kind === 'transfer') {
    if (form.source_warehouse_id === '' || form.source_warehouse_id == null) {
      errors.source_warehouse_id = 'required'
    }
    if (form.destination_warehouse_id === '' || form.destination_warehouse_id == null) {
      errors.destination_warehouse_id = 'required'
    }
    if (
      form.source_warehouse_id !== '' &&
      form.destination_warehouse_id !== '' &&
      Number(form.source_warehouse_id) === Number(form.destination_warehouse_id)
    ) {
      errors.destination_warehouse_id = 'sameWarehouse'
    }
  } else if (form.warehouse_id === '' || form.warehouse_id == null) {
    errors.warehouse_id = 'required'
  }

  if (form.inventory_item_id === '' || form.inventory_item_id == null) {
    errors.inventory_item_id = 'required'
  }

  if (kind === 'adjust' && (form.direction === '' || form.direction == null)) {
    errors.direction = 'required'
  }

  return errors
}

export function mapInventoryErrorCode(code: string | undefined): string {
  switch (code) {
    case 'WAREHOUSE_INACTIVE':
    case 'WAREHOUSE_IN_USE':
    case 'INVENTORY_ITEM_INACTIVE':
    case 'INVENTORY_ITEM_IN_USE':
    case 'INVENTORY_CATEGORY_IN_USE':
    case 'INVENTORY_CATEGORY_INVALID':
    case 'INVENTORY_INVALID_QUANTITY':
    case 'INVENTORY_INSUFFICIENT_STOCK':
    case 'INVENTORY_TRANSFER_SAME_WAREHOUSE':
    case 'INVENTORY_ADJUSTMENT_REASON_REQUIRED':
    case 'INVENTORY_REASON_REQUIRED':
    case 'INVENTORY_IMMUTABLE':
      return code
    default:
      return 'generic'
  }
}

export function emptyStockActionForm(
  defaults: Partial<StockActionFormState> = {},
): StockActionFormState {
  return {
    warehouse_id: '',
    inventory_item_id: '',
    quantity: '',
    reason: '',
    reference: '',
    as_opening: false,
    source_warehouse_id: '',
    destination_warehouse_id: '',
    direction: '',
    ...defaults,
  }
}

export function computeWarehouseStockKpis(
  balances: Array<{ stock_state: StockState; item: { id: number } | null }>,
): { distinctItems: number; lowStock: number; outOfStock: number } {
  const itemIds = new Set<number>()
  let lowStock = 0
  let outOfStock = 0

  for (const balance of balances) {
    if (balance.item?.id != null) {
      itemIds.add(balance.item.id)
    }
    if (balance.stock_state === 'low') lowStock += 1
    if (balance.stock_state === 'out_of_stock') outOfStock += 1
  }

  return {
    distinctItems: itemIds.size,
    lowStock,
    outOfStock,
  }
}
