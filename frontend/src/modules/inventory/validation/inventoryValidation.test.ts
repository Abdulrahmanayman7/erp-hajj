import { describe, expect, it } from 'vitest'

import type { StockActionFormState } from '../types/stock'
import type { WarehouseFormState } from '../types/warehouses'
import {
  computeWarehouseStockKpis,
  isPositiveQuantity,
  mapInventoryErrorCode,
  resolveInventoryListState,
  validateStockActionForm,
  validateWarehouseForm,
} from './inventoryValidation'

function emptyWarehouseForm(overrides: Partial<WarehouseFormState> = {}): WarehouseFormState {
  return {
    name: '',
    description: '',
    location: '',
    organization_unit_id: '',
    responsible_employee_id: '',
    is_active: true,
    notes: '',
    ...overrides,
  }
}

function emptyStockForm(overrides: Partial<StockActionFormState> = {}): StockActionFormState {
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
    ...overrides,
  }
}

describe('resolveInventoryListState', () => {
  it('resolves loading / error / empty / ready', () => {
    expect(resolveInventoryListState({ isLoading: true, isError: false, count: 0 })).toBe('loading')
    expect(resolveInventoryListState({ isLoading: false, isError: true, count: 0 })).toBe('error')
    expect(resolveInventoryListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
    expect(resolveInventoryListState({ isLoading: false, isError: false, count: 3 })).toBe('ready')
  })
})

describe('isPositiveQuantity', () => {
  it('accepts finite values greater than zero', () => {
    expect(isPositiveQuantity('1')).toBe(true)
    expect(isPositiveQuantity('0.5')).toBe(true)
    expect(isPositiveQuantity(' 2 ')).toBe(true)
  })

  it('rejects empty, zero, negative, and non-numeric values', () => {
    expect(isPositiveQuantity('')).toBe(false)
    expect(isPositiveQuantity('   ')).toBe(false)
    expect(isPositiveQuantity('0')).toBe(false)
    expect(isPositiveQuantity('-1')).toBe(false)
    expect(isPositiveQuantity('abc')).toBe(false)
  })
})

describe('validateStockActionForm', () => {
  it('flags transfer when source and destination warehouses match', () => {
    const errors = validateStockActionForm(
      'transfer',
      emptyStockForm({
        source_warehouse_id: 1,
        destination_warehouse_id: 1,
        inventory_item_id: 2,
        quantity: '5',
        reason: 'نقل',
      }),
    )

    expect(errors.destination_warehouse_id).toBe('sameWarehouse')
  })

  it('requires reason for stock actions', () => {
    const errors = validateStockActionForm(
      'receive',
      emptyStockForm({
        warehouse_id: 1,
        inventory_item_id: 2,
        quantity: '3',
        reason: '  ',
      }),
    )

    expect(errors.reason).toBe('required')
  })

  it('requires direction for adjust actions', () => {
    const errors = validateStockActionForm(
      'adjust',
      emptyStockForm({
        warehouse_id: 1,
        inventory_item_id: 2,
        quantity: '1',
        reason: 'جرد',
        direction: '',
      }),
    )

    expect(errors.direction).toBe('required')
  })

  it('passes a valid transfer', () => {
    const errors = validateStockActionForm(
      'transfer',
      emptyStockForm({
        source_warehouse_id: 1,
        destination_warehouse_id: 2,
        inventory_item_id: 3,
        quantity: '4',
        reason: 'تحويل',
      }),
    )

    expect(errors).toEqual({})
  })
})

describe('validateWarehouseForm', () => {
  it('requires a non-empty name', () => {
    expect(validateWarehouseForm(emptyWarehouseForm())).toEqual({ name: 'required' })
    expect(validateWarehouseForm(emptyWarehouseForm({ name: '   ' }))).toEqual({ name: 'required' })
    expect(validateWarehouseForm(emptyWarehouseForm({ name: 'المستودع الرئيسي' }))).toEqual({})
  })
})

describe('computeWarehouseStockKpis', () => {
  it('counts distinct items and stock states without aggregating quantities', () => {
    const kpis = computeWarehouseStockKpis([
      { stock_state: 'normal', item: { id: 1 } },
      { stock_state: 'low', item: { id: 1 } },
      { stock_state: 'low', item: { id: 2 } },
      { stock_state: 'out_of_stock', item: { id: 3 } },
      { stock_state: 'out_of_stock', item: null },
    ])

    expect(kpis).toEqual({
      distinctItems: 3,
      lowStock: 2,
      outOfStock: 2,
    })
  })
})

describe('mapInventoryErrorCode', () => {
  it('maps known domain codes and falls back to generic', () => {
    expect(mapInventoryErrorCode('WAREHOUSE_INACTIVE')).toBe('WAREHOUSE_INACTIVE')
    expect(mapInventoryErrorCode('INVENTORY_INSUFFICIENT_STOCK')).toBe(
      'INVENTORY_INSUFFICIENT_STOCK',
    )
    expect(mapInventoryErrorCode('INVENTORY_TRANSFER_SAME_WAREHOUSE')).toBe(
      'INVENTORY_TRANSFER_SAME_WAREHOUSE',
    )
    expect(mapInventoryErrorCode('UNKNOWN_CODE')).toBe('generic')
    expect(mapInventoryErrorCode(undefined)).toBe('generic')
  })
})
