# MVP Scope

> **Status:** Approved (fixed scope)
> **Last updated:** 2026-08-12

## Purpose

Define the agreed, fixed MVP scope. Anything not listed here is out of scope (see [OUT_OF_SCOPE.md](OUT_OF_SCOPE.md)). Adding features beyond this list requires an approved Change Request.

## MVP Modules

1. Authentication
2. Administrative dashboard (لوحة التحكم الإدارية)
3. Users management
4. Roles management
5. Permissions management
6. Organizational structure (الهيكل التنظيمي)
7. Employees management (الموظفون)
8. Supervisors management (المشرفون)
9. Contracts management (العقود)
10. Meetings management (الاجتماعات)
11. Decisions management (القرارات)
12. Tasks and assignments (المهام والتكليفات)
13. Documents and archiving (الوثائق والأرشفة)
14. Warehouses (المستودعات)
15. Basic inventory management (إدارة المخزون الأساسية)
16. Custodies (العُهد)
17. Assets (الأصول)
18. Notifications required by MVP modules (الإشعارات)
19. Audit trail for critical operations (سجل التدقيق)
20. Multi-tenant foundation
21. System-level settings required by the above modules — **specified** ([15-system-settings/](../09-modules/15-system-settings/), [ADR-0016](../10-decisions/ADR-0016-TYPED-TENANT-SETTINGS-AND-RESOLUTION.md)); **implementation pending**

Module-level documentation lives in [docs/09-modules/](../09-modules/).

## Important Domain Rules

- **Decisions are a separate business entity.** Do not merge Decisions into Tasks.
- **Assets and Custodies are separate entities.** An Asset is a physical or registered resource; a Custody is the handover or assignment of an asset to an employee or supervisor.
- **Inventory Items and Assets are separate concepts.** Inventory items are stock-tracked by quantity; assets are individually tracked.

## Central Workflows

1. Meeting → Recommendation → Decision → Task(s) → Responsible Person → Execution → Measurement → Closure
2. Contract Draft → Review → Approval → Signature → Execution → Closure or Renewal
3. Asset → Available → Assigned as Custody → In Use → Returned → Available / Maintenance / Retired
4. Inventory: Purchase/Addition → Storage → Transfer/Issue/Return → Updated Balance

See [CORE_WORKFLOWS.md](../01-business/CORE_WORKFLOWS.md).

## Rules

- The MVP scope is **fixed**. No new business requirements may be invented.
- Delivery is web only; the API must remain consumable by future mobile apps.
- Every module must respect tenant isolation, permissions, and audit requirements — no module may bypass them.
