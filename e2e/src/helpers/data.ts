export function firstId(json: unknown): number | null {
  const body = json as {
    data?: Array<{ id?: number }> | { data?: Array<{ id?: number }>; id?: number }
    id?: number
  }
  if (!body) return null
  if (typeof body.id === 'number') return body.id
  if (Array.isArray(body.data) && typeof body.data[0]?.id === 'number') return body.data[0].id
  if (body.data && !Array.isArray(body.data)) {
    if (typeof body.data.id === 'number') return body.data.id
    if (Array.isArray(body.data.data) && typeof body.data.data[0]?.id === 'number') {
      return body.data.data[0].id
    }
  }
  return null
}

export function stamp(prefix = 'E2E'): string {
  return `${prefix}_${Date.now()}`
}

export function acceptCreateStatus(status: number): boolean {
  return [200, 201, 403, 422].includes(status)
}

export function acceptReadStatus(status: number): boolean {
  return [200, 403, 404, 422].includes(status)
}
