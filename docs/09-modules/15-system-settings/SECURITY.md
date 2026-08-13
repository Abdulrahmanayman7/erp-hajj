# System Settings — Security threat model

> Companion to [SECURITY_BASELINE.md](../../06-security/SECURITY_BASELINE.md)
> **Sprint 020 — implemented**

| Threat | Control |
|---|---|
| Cross-tenant read/update | Context-only tenant; TenantOwned where KV used; Pest isolation |
| Forged `tenant_id` | Ignored/rejected; never mass-assigned |
| Arbitrary setting key injection | Strict catalog; unknown → 422 |
| Secret storage misuse | Forbidden fields; no env editor |
| Malicious HTML in strings | Validation max length; output escaped by Vue; no HTML accept |
| Privilege escalation via settings | Policies on view/update; GM lacks update by default |
| Threshold out-of-bounds | Thresholds not tenant-editable in MVP |
| Unaudited change | `TENANT_SETTINGS_UPDATED` required on success |
| Stale cache after update | No server settings cache; FE invalidates queries |
| Mass assignment | Form Request + explicit fillable/casts; immutable guards |
| Disabled/suspended tenant | Existing lifecycle middleware |
| Platform/settings confusion | Distinct routes/permissions; this slice is tenant-only |
