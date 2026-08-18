import type { AppSelectOption } from '@/shared/components/AppSelect.vue'

export function toSelectId(value: string | number | null | undefined): number | '' {
  if (value === '' || value === null || value === undefined) return ''
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : ''
}

export function toSelectNullableId(value: string | number | null | undefined): number | null {
  const id = toSelectId(value)
  return id === '' ? null : id
}

export function employeeSelectOption(employee: {
  id: number
  full_name: string
  employee_number?: string | null
  organization_unit?: { name: string } | null
}): AppSelectOption {
  return {
    value: employee.id,
    label: employee.full_name,
    hint: [employee.employee_number, employee.organization_unit?.name].filter(Boolean).join(' · '),
  }
}

export function userSelectOption(user: { id: number; name: string; email: string }): AppSelectOption {
  return {
    value: user.id,
    label: user.name,
    hint: user.email,
  }
}

export function warehouseSelectOption(warehouse: {
  id: number
  name: string
  warehouse_number?: string | null
}): AppSelectOption {
  return {
    value: warehouse.id,
    label: warehouse.name,
    hint: warehouse.warehouse_number ?? undefined,
  }
}

export function inventoryItemSelectOption(item: {
  id: number
  name: string
  item_number: string
  category?: { name: string } | null
}): AppSelectOption {
  return {
    value: item.id,
    label: item.name,
    hint: [item.item_number, item.category?.name].filter(Boolean).join(' · '),
  }
}

export function numberedEntityOption(entity: {
  id: number
  title: string
  contract_number?: string
  meeting_number?: string
  decision_number?: string
  task_number?: string
}): AppSelectOption {
  const number =
    entity.contract_number ??
    entity.meeting_number ??
    entity.decision_number ??
    entity.task_number ??
    ''
  return {
    value: entity.id,
    label: number ? `${number} — ${entity.title}` : entity.title,
    hint: number || undefined,
  }
}

export function namedCodeOption(entity: {
  id: number
  name: string
  code?: string | null
}): AppSelectOption {
  return {
    value: entity.id,
    label: entity.name,
    hint: entity.code ?? undefined,
  }
}

export function roleSelectOption(role: { id: number; name: string; code?: string | null }): AppSelectOption {
  return namedCodeOption(role)
}

export function positionSelectOption(position: {
  id: number
  name: string
  code?: string | null
}): AppSelectOption {
  return namedCodeOption(position)
}
