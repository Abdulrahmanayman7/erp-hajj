# Assets and Custodies — Permissions

> **Status:** Specified (Sprint 015) — **not implemented** (named for catalog; seed at implementation)
> **Last updated:** 2026-08-11

Capabilities use `module.action`. Policies never check role names. Frontend gates are UX-only.

## Catalog (final MVP)

| Permission | Meaning |
|---|---|
| `assets.view` | List/view assets, categories (read), custodies; required base for holder self-view |
| `assets.create` | Register assets |
| `assets.update` | Update asset metadata; category write; maintenance/restore actions |
| `assets.delete` | Hard-delete unused assets |
| `assets.assign` | Assign custody (audited) |
| `assets.return` | Return custody (audited) |
| `assets.retire` | Retire or declare lost (audited; sensitive) |

**No** separate `custodies.*` verbs — custody mutations use `assets.assign` / `assets.return`.
**Not seeded:** custody correction, acknowledgment, finance, maintenance-work-order permissions.

### Action → permission map

| API / UX | Permission / access |
|---|---|
| Asset/category/custody list | `assets.view` |
| Asset/custody show | `assets.view` **or** holder self-view |
| Asset create | `assets.create` |
| Asset patch / categories write / maintenance / restore | `assets.update` |
| Asset delete | `assets.delete` |
| Assign | `assets.assign` |
| Return | `assets.return` |
| Retire / declare-lost | `assets.retire` |
| My custodies | `assets.view` + Employee↔User link (scoped) |

## Policies

### `AssetPolicy`

| Ability | Rule |
|---|---|
| `viewAny` | `assets.view` |
| `view` | (`assets.view` **or** `holderSelf`) + same tenant |
| `create` | `assets.create` |
| `update` | `assets.update` + same tenant |
| `delete` | `assets.delete` + same tenant |
| `assign` | `assets.assign` + same tenant |
| `returnCustody` | `assets.return` + same tenant |
| `retire` | `assets.retire` + same tenant |

### `AssetCustodyPolicy`

| Ability | Rule |
|---|---|
| `viewAny` | `assets.view` |
| `view` | (`assets.view` **or** `holderSelf`) + same tenant |

### Holder self-view (`holderSelf`) — ADR-0012

True iff:

1. Actor has `assets.view`, and
2. Actor’s linked Employee (`employees.user_id = actor.id`) equals the custody `employee_id` (for custody) or the asset’s **active** custody employee (for asset show), and
3. Same tenant.

Self-view **cannot** assign, return, update, delete, retire, or manage categories.
List `/assets` remains full-tenant only with `assets.view` (no automatic “only mine” filter unless client calls `/my-custodies`).

## Default system role template grants

| Role template | view | create | update | delete | assign | return | retire |
|---|---|---|---|---|---|---|---|
| Tenant Owner | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| General Manager | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Department Manager | ✓ | ✓ | ✓ | — | ✓ | ✓ | — |
| Supervisor | ✓ | — | — | — | — | — | — |
| Employee (base) | ✓ | — | — | — | — | — | — |
| Auditor | ✓ | — | — | — | — | — | — |

Owner via full catalog. Employee/Supervisor use **self-view** for own assigned assets via `/my-custodies` + asset show.
Do not wipe custom roles when seeding templates.
`assets.retire` is high-risk (document in PermissionCatalog high-risk list at implementation).
