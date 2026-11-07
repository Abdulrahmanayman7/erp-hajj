# S08 — Permissions Design

> **Status:** `IMPLEMENTED` in `PermissionCatalog` on feature branch (not Owner Accepted / merged)  
> Binding: [OWNER_DECISIONS.md](OWNER_DECISIONS.md) S08-D06 + [OWNER_DECISIONS_D11_D20.md](OWNER_DECISIONS_D11_D20.md) S08-D20

## 1. Permission catalog entries

| name | display_name (AR) | module | description |
|---|---|---|---|
| `season_templates.view` | عرض قوالب المواسم | `season_templates` | عرض مكتبة القوالب وتفاصيلها |
| `season_templates.manage` | إدارة قوالب المواسم | `season_templates` | إنشاء/تعديل/اعتماد/أرشفة القوالب |
| `season_copy.preview` | معاينة النسخ الآمن | `season_copy` | إنشاء وقراءة معاينات النسخ |
| `season_copy.execute` | تنفيذ النسخ الآمن | `season_copy` | تنفيذ النسخ بعد معاينة سارية |
| `season_copy.history` | سجل عمليات النسخ | `season_copy` | عرض سجل محاولات النسخ |
| `season_reminders.view` | عرض تذكيرات الموسم | `season_reminders` | عرض قواعد تذكير الموسم (D20) |
| `season_reminders.manage` | إدارة تذكيرات الموسم | `season_reminders` | إنشاء/تعديل/تفعيل/إيقاف/أرشفة القواعد (D20) |

All backend-enforced via Policies/Gates. Deny by default. No persona bypass (including Tenant Owner).  
Executing `season_reminder_rules` copy also requires `season_reminders.manage` (plus `season_copy.execute`). Contract copy still requires `contracts.create`.

## 2. Owner role matrix → catalog mapping

| Owner label | View | Manage | Preview | Execute | History |
|---|---|---|---|---|---|
| Tenant Owner | Y | Y | Y | Y | Y |
| Tenant Administrator | Y | Y | Y | Y | Y |
| Operations Manager | Y | Y | Y | N | explicit only |
| Department Supervisor | Y | N | N | N | N |
| Regular User | N | N | N | N | N |

### Mapping to existing `PermissionCatalog::roleTemplates()` codes

| Owner label | Proposed `code` | Grant set (proposed) |
|---|---|---|
| Tenant Owner | `tenant_owner` | All five (via `$all` once catalog entries exist; unless marked sensitiveExplicitOnly — **not** proposed as sensitive) |
| Tenant Administrator | `general_manager` (**OPEN-Q**) | all five |
| Operations Manager | **OPEN-Q** new `operations_manager` *or* subset of `general_manager` | view+manage+preview; history only if explicitly assigned; **no** execute |
| Department Supervisor | `supervisor` | `season_templates.view` only |
| Regular User | `employee` / `read_only` | none |

Custom roles: Tenant Owner may assign any of the five verbs within authorization rules (existing role management module).

## 3. Policy rules (proposed)

| Ability | Policy check |
|---|---|
| view template/run/preview | permission + same tenant |
| manage template | permission + status transition rules |
| preview | permission + source/target tenant match |
| execute | permission + valid preview ownership + draft target |
| history | permission |

## 4. Sensitive / explicit-only

S08-D06 does **not** mark execute as `sensitiveExplicitOnly`. Operations Manager execute remains **No** via template matrix. If Owner later wants execute to be explicit-only for all non-owners, update this doc + catalog `sensitiveExplicitOnlyPermissions()`.
