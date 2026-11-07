export type TemplateStatus = 'draft' | 'approved' | 'archived'
export const TEMPLATE_STATUSES: readonly TemplateStatus[] = ['draft', 'approved', 'archived']

export interface TemplateItemSelection {
  codes?: string[]
}

export interface SeasonTemplateItem {
  entity_kind: string
  sort_order: number
  selection: TemplateItemSelection | null
}

export interface TemplateUserSummary {
  id: number
  name: string
}

export interface SeasonTemplate {
  id: number
  template_number: string
  name: string
  description: string | null
  status: TemplateStatus
  matrix_version: string
  items: SeasonTemplateItem[]
  approved_at: string | null
  archived_at: string | null
  created_at: string | null
  updated_at: string | null
  created_by: TemplateUserSummary | null
  approved_by: TemplateUserSummary | null
}

export interface SeasonTemplatesListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListSeasonTemplatesParams {
  q?: string
  status?: TemplateStatus | 'all' | ''
  page?: number
  per_page?: number
}

export interface TemplateItemPayload {
  entity_kind: string
  selection?: { codes: string[] } | null
}

export interface CreateSeasonTemplatePayload {
  name: string
  description?: string | null
  items: TemplateItemPayload[]
}

export type UpdateSeasonTemplatePayload = Partial<CreateSeasonTemplatePayload>

export interface TemplateFormState {
  name: string
  description: string
  entity_kinds: string[]
  /** Optional natural-key codes per entity kind, newline/comma separated. */
  codes: Record<string, string>
}
