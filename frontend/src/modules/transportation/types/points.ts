export type TransportPointType = 'airport' | 'station' | 'gathering_point' | 'custom'

export const POINT_TYPES: TransportPointType[] = [
  'airport',
  'station',
  'gathering_point',
  'custom',
]

export interface TransportPoint {
  id: number
  code: string
  name: string
  point_type: TransportPointType
  is_active: boolean
  notes: string | null
  created_at: string | null
  updated_at: string | null
}

export interface PointFormState {
  code: string
  name: string
  point_type: TransportPointType | ''
  notes: string
}

export interface ListPointsParams {
  search?: string
  is_active?: boolean | ''
  point_type?: TransportPointType | ''
  page?: number
  per_page?: number
}
