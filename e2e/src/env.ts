function required(name: string): string {
  const value = process.env[name]?.trim()
  if (!value) {
    throw new Error(
      `Missing ${name}. Copy e2e/.env.e2e.example to e2e/.env.e2e and set credentials.`,
    )
  }
  return value
}

export const e2eEnv = {
  baseURL: process.env.E2E_BASE_URL || 'https://erp-hij.esappx.com',
  apiURL: process.env.E2E_API_URL || process.env.E2E_BASE_URL || 'https://erp-hij.esappx.com',
  email: () => required('E2E_EMAIL'),
  password: () => required('E2E_PASSWORD'),
  tenantHint: process.env.E2E_TENANT_HINT || 'perf-large',
}
