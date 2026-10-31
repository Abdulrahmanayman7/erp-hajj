export type VehicleClass = 'bus' | 'minibus' | 'car'
export type ServiceLevel = 'standard' | 'premium'

export const VEHICLE_CLASSES: VehicleClass[] = ['bus', 'minibus', 'car']
export const SERVICE_LEVELS: ServiceLevel[] = ['standard', 'premium']

export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface VehicleAssetSummary {
  id: number
  asset_number: string
  name: string
}

export interface VehicleContractSummary {
  id: number
  contract_number: string
  title: string
}

export interface TransportVehicle {
  id: number
  vehicle_number: string
  plate_number: string
  vehicle_class: VehicleClass
  asset_id: number | null
  asset?: VehicleAssetSummary | null
  contract_id: number | null
  contract?: VehicleContractSummary | null
  is_active: boolean
  notes: string | null
  created_at: string | null
  updated_at: string | null
}

export interface VehicleLinksFormState {
  asset_id: number | ''
  contract_id: number | ''
}

export interface CrewPersonSummary {
  id: number
  full_name: string
  employee_number?: string | null
}

export interface DefaultCrewFormState {
  driver_kind: 'none' | 'employee' | 'external'
  default_driver_employee_id: number | ''
  default_external_driver_id: number | ''
  default_supervisor_employee_id: number | ''
}

export interface VehicleFormState {
  plate_number: string
  vehicle_class: VehicleClass | ''
  notes: string
}

export interface ListVehiclesParams {
  search?: string
  is_active?: boolean | ''
  vehicle_class?: VehicleClass | ''
  page?: number
  per_page?: number
}

export interface TransportExternalDriver {
  id: number
  full_name: string
  phone: string | null
  is_active: boolean
  notes: string | null
  created_at: string | null
  updated_at: string | null
}

export interface DriverFormState {
  full_name: string
  phone: string
  notes: string
}

export interface ListDriversParams {
  search?: string
  is_active?: boolean | ''
  page?: number
  per_page?: number
}

export interface SeasonVehicleVehicleSummary {
  id: number
  vehicle_number: string
  plate_number: string
  vehicle_class: VehicleClass
}

export interface TransportSeasonVehicle {
  id: number
  season_id: number
  transport_vehicle_id: number
  vehicle?: SeasonVehicleVehicleSummary
  operational_capacity: number
  service_level: ServiceLevel
  default_driver_employee?: CrewPersonSummary | null
  default_external_driver?: CrewPersonSummary | null
  default_supervisor_employee?: CrewPersonSummary | null
  is_active: boolean
  notes: string | null
  created_at: string | null
  updated_at: string | null
}

export interface SeasonVehicleFormState {
  transport_vehicle_id: number | ''
  operational_capacity: string
  service_level: ServiceLevel | ''
  notes: string
}

export interface ListSeasonVehiclesParams {
  is_active?: boolean | ''
  page?: number
  per_page?: number
  seasonId?: number | null
}

export type ListViewState = 'loading' | 'error' | 'empty' | 'ready' | 'need_season'
