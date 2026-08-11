export type StockState = 'normal' | 'low' | 'out_of_stock'

export const STOCK_STATES: StockState[] = ['normal', 'low', 'out_of_stock']

export interface BalanceWarehouseSummary {
  id: number
  warehouse_number: string
  name: string
}

export interface BalanceItemCategorySummary {
  id: number
  name: string
}

export interface BalanceItemSummary {
  id: number
  item_number: string
  name: string
  unit: string
  minimum_stock: string | number | null
  category?: BalanceItemCategorySummary | null
}

export interface InventoryBalance {
  id: number
  on_hand: string | number
  stock_state: StockState
  warehouse: BalanceWarehouseSummary | null
  item: BalanceItemSummary | null
  created_at: string | null
  updated_at: string | null
}

export interface InventoryBalancesListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListInventoryBalancesParams {
  warehouse_id?: number | ''
  inventory_item_id?: number | ''
  category_id?: number | ''
  stock_state?: StockState | ''
  search?: string
  page?: number
  per_page?: number
  sort?: string
  direction?: string
}

export type InventoryBalanceListState = 'loading' | 'error' | 'empty' | 'ready'
