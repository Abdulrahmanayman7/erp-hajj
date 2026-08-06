# Tenancy — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-06

- A tenant represents an independent Hajj campaign company or organization.
- Cross-tenant access is impossible; all tenant-owned data is automatically scoped.
- `tenant_id` is never trusted from request payloads; tenant context comes from the authenticated user.
- Each tenant user belongs to exactly one tenant in the MVP.
- A suspended tenant's users must not be able to operate in the system (exact behavior on suspension: TBD).
- Platform Super Admin does not automatically access tenant business data; such access must be explicitly authorized and always audited.
- Tenant settings changes are audited.

## TBD

- Tenant provisioning/onboarding flow (who creates the first Tenant Owner user): TBD.
- Suspension behavior details (read-only vs. full lockout): TBD.
- Tenant deletion policy: TBD.
