# Risks & Complaints — Permissions (Implemented)

> **Status:** PART 6 — registered in `PermissionCatalog`
> Pattern: immutable `module.action`; deny-by-default; Policies/Gates; no persona bypass.
> Binding: P01, P02, P03, P04, D09.

## 1. Module keys

- `risks` — Arabic label **المخاطر**
- `complaints` — Arabic label **الشكاوى**

## 2. `risks.*`

| Permission | Intent | Sensitive? |
|---|---|---|
| `risks.view` | List/show + **timeline** | No |
| `risks.create` | Create `identified` risk | No |
| `risks.update` | Patch mutable fields while not closed | No |
| `risks.assess` | Enter/update assessment (L/I) | No |
| `risks.approve_assessment` | Approve assessment + treatment → `approved` | **Yes — sensitiveExplicitOnly** |
| `risks.manage_treatment` | Create/replace treatment plan | No |
| `risks.assign` | Assign owner / `approved`→`treating` | No |
| `risks.close` | Close treated risk with outcome | No |
| `risks.delete` | Soft-delete `identified` only | No |

## 3. `complaints.*`

| Permission | Intent | Sensitive? |
|---|---|---|
| `complaints.view` | List/show + notes + **timeline** | No |
| `complaints.create` | Staff intake `new` | No |
| `complaints.update` | Patch; `new`→`under_review` | No |
| `complaints.assign` | Set assignee; `under_review`→`in_progress` | No |
| `complaints.escalate` | Escalate (also allowed for assignee User without this grant) | No |
| `complaints.resolve` | Resolution → `resolved` (assignee match enforced) | No |
| `complaints.close` | Close + reason | **Yes — sensitiveExplicitOnly** |
| `complaints.reopen` | Reopen + reason | **Yes — sensitiveExplicitOnly** |
| `complaints.add_note` | Append follow-up note | No |
| `complaints.delete` | Soft-delete `new` only | No |

## 4. Separation of duties (P02)

`risks.approve_assessment` is necessary but **not sufficient** when `actor.id === risk.created_by` → `RISK_SELF_APPROVAL_FORBIDDEN`.

## 5. Escalation dual path (D03)

Allowed when:

1. Actor has `complaints.escalate`, **or**
2. Actor's linked `employee_id` equals `assignee_employee_id`

UI mirrors this using `AuthUser.employee_id`; server remains authoritative.

## 6. Template grant matrix

| Permission | `tenant_owner` via non-sensitive `$all` | Explicit only |
|---|---|---|
| Non-sensitive risks/complaints actions | Yes (typical `$all`) | — |
| `risks.approve_assessment` | **No** | Yes |
| `complaints.close` | **No** | Yes |
| `complaints.reopen` | **No** | Yes |

## 7. Capabilities endpoint gate

`GET /operational-links/capabilities` requires any of: `risks.view`, `complaints.view`, `risks.create`, `complaints.create`.
