# Decisions — Permissions

> **Status:** Specified (Sprint 011) — **not implemented** (named in catalog; seed at implementation)  
> **Last updated:** 2026-08-10

Capabilities use `module.action` vocabulary. Policies check capabilities only — never role names. Frontend gates are UX-only; backend is authoritative.

## Catalog (final MVP)

| Permission | Meaning |
|---|---|
| `decisions.view` | List and view Decisions + history |
| `decisions.create` | Create standalone or from recommendation |
| `decisions.update` | Edit draft; submit; cancel (draft \| pending_approval) |
| `decisions.approve` | Approve; return-to-draft |
| `decisions.close` | Close approved Decision |
| `decisions.delete` | Hard-delete untouched draft |

**Not added:** `decisions.submit`, `decisions.reject`, `decisions.cancel`, `decisions.activate` — mapped onto existing verbs to avoid micro-permissions.

### Action → permission map

| API action | Permission |
|---|---|
| GET list/show | `decisions.view` |
| POST create | `decisions.create` |
| PATCH draft | `decisions.update` |
| POST submit | `decisions.update` |
| POST cancel | `decisions.update` |
| POST return-draft | `decisions.approve` |
| POST approve | `decisions.approve` |
| POST close | `decisions.close` |
| DELETE untouched draft | `decisions.delete` |

## Policy: `DecisionPolicy`

| Ability | Rule |
|---|---|
| `viewAny` / `view` | `decisions.view` + tenant scope |
| `create` | `decisions.create` |
| `update` | `decisions.update` + draft for content PATCH; transition rules for submit/cancel |
| `approve` | `decisions.approve` + status `pending_approval` |
| `close` | `decisions.close` + status `approved` |
| `delete` | `decisions.delete` + untouched draft rule |

No persona bypass — including Tenant Owner (must hold the capability via role grants).

## Default system role template grants

| Role template | view | create | update | approve | close | delete |
|---|---|---|---|---|---|---|
| Tenant Owner | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| General Manager | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Department Manager | ✓ | ✓ | ✓ | — | — | ✓* |
| Supervisor | — | — | — | — | — | — |
| Auditor | ✓ | — | — | — | — | — |
| Employee (base) | — | — | — | — | — | — |

\*Department Manager: draft delete of own or any untouched draft under `decisions.delete` if granted — **grant delete** for Department Manager templates so they can remove mistaken drafts they create. **Do not** grant `approve` or `close` by default (approval authority stays narrow).

Exact seed values follow this table at implementation; custom tenant roles may diverge.

## Meetings UI affordance

`إنشاء قرار` on a final recommendation requires **`decisions.create`** (not a meetings permission). Viewing the meeting still requires `meetings.view`.
