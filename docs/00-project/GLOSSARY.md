# Glossary

> **Status:** Draft
> **Last updated:** 2026-08-10

## Purpose

Define the shared vocabulary of the project in English with the Arabic business terms preserved. Use these terms consistently in code, documentation, and UI.

## Terms

| English | Arabic | Definition |
|---|---|---|
| Tenant | مستأجر | Technical term for an isolated organization on the platform. Business-facing term: **Organization / Campaign Company**. |
| Organization / Campaign Company | منظمة / شركة حملة حج | An independent Hajj campaign company; one tenant. |
| Organizational structure | الهيكل التنظيمي | Hierarchy of departments, sections, and units inside a tenant. |
| Department | إدارة / قسم رئيسي | Top-level organizational unit type. |
| Section | قسم | Mid-level organizational unit type. |
| Unit | وحدة | Lower-level organizational unit type. |
| Position / Job title | منصب / مسمى وظيفي | Tenant-owned `positions` catalog (Sprint 008 spec; ADR-0005); optional on Employee. |
| Employee | موظف | Primary personnel record (optional User link; required org unit; optional direct supervisor). |
| Supervisor | مشرف | Reporting via `employees.supervisor_id`. RBAC role `supervisor` is separate. Not unit manager. |
| Employee number | الرقم الوظيفي | Tenant-unique server-generated id (e.g. `EMP-000001`); not an auth input. |
| Contract | عقد | A formal agreement managed through the controlled contract lifecycle (Draft→…→Closure/Renewal). Numbered `CTR-######` per tenant (Sprint 009). |
| Contract category | تصنيف العقد | Tenant-owned catalog entry classifying a contract (ADR-0006). |
| Meeting | اجتماع | A formal administrative meeting with agenda, attendees, and minutes. Numbered `MTG-######` per tenant (Sprint 010). |
| Minutes | محضر الاجتماع | The formal record of a meeting (`minutes_body` text on the meeting). |
| Recommendation | توصية | A first-class meeting outcome (`meeting_recommendations`) that may become a Decision; statuses `draft` \| `final` only — not a Decision workflow. |
| Decision | قرار | Formal governance entity (`DEC-######`); may link to at most one final recommendation via `source_recommendation_id`, or exist standalone; may later generate tasks (Tasks own `decision_id`). |
| Task | مهمة | Execution work item (`TSK-######`); optional link to Decision via `decision_id`; single Employee assignee; progress + completion notes for measurement (Sprint 012 spec). |
| Responsible person | الشخص المسؤول | Do not conflate: Decision `responsible_employee_id` (governance follow-up) vs Task `assigned_to_employee_id` (execution assignee). |
| Document | وثيقة / مستند | A single uploaded file plus metadata in the central document center (`DOC-######`), optionally linked to one business entity (ADR-0010). |
| Archiving | أرشفة | Document status transition to `archived` (file retained); distinct from hard delete. |
| Warehouse | مستودع | Storage location (`WH-######`); optional org unit + responsible employee metadata (Sprint 014 spec). |
| Inventory item | صنف مخزون | Quantity-tracked item (`ITM-######`); fixed base unit; not an Asset. |
| Inventory balance | رصيد مخزون | Materialized on-hand qty per warehouse+item (cache; ledger-authored). |
| Inventory movement | حركة مخزون | Append-only stock ledger row (`MOV-######`): opening/receipt/issue/return/transfer/adjustment. |
| Asset | أصل | Individually tracked resource (`AST-######`); statuses include available/in_use/maintenance/damaged/retired/lost (Sprint 015 spec). |
| Custody | عهدة | Append-only handover of an Asset to an Employee (`CUS-######`); at most one active per asset; history immutable after return. |
| Notification | إشعار | A system alert required by MVP modules (e.g. contract expiry). |
| Audit trail | سجل التدقيق | Immutable record of who did what, when, from where, and in which tenant. |
| Role | دور | A named, dynamic set of permissions assigned to users. |
| Permission | صلاحية | The right to perform a specific action, named `module.action`. |
| Dashboard | لوحة التحكم | The administrative dashboard with tenant-scoped, permission-aware indicators. |

## Rules

- New domain terms must be added here before they are used in code or UI.
- Arabic terms shown here are the reference UI wording unless the business specifies otherwise (TBD per module).
