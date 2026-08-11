# Glossary

> **Status:** Draft
> **Last updated:** 2026-08-06

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
| Position / Job title | منصب / مسمى وظيفي | The job title held by an employee. |
| Employee | موظف | The primary staff entity. |
| Supervisor | مشرف | An employee with supervisory classification and permissions (not a separate identity record). |
| Contract | عقد | A formal agreement managed through the controlled contract lifecycle. |
| Meeting | اجتماع | A formal administrative meeting with agenda, attendees, and minutes. |
| Minutes | محضر الاجتماع | The formal record of a meeting. |
| Recommendation | توصية | A meeting outcome that may become a decision. |
| Decision | قرار | A separate business entity; may originate from a meeting or exist independently, and may generate tasks. |
| Task | مهمة | A unit of work assigned to responsible users/employees, tracked to execution and measurement. |
| Responsible person | الشخص المسؤول | The person accountable for executing a task or decision. |
| Document | وثيقة / مستند | A file managed by the central document center, linkable to business entities. |
| Archiving | أرشفة | Retention and organized storage of documents (archive/soft delete). |
| Warehouse | مستودع | A physical or logical storage location. |
| Inventory item | صنف مخزون | A stock-tracked item; quantities derive from transactions. |
| Inventory transaction | حركة مخزون | A movement affecting quantity: addition, issue, return, transfer, adjustment. |
| Asset | أصل | An individually tracked physical or registered resource (device, vehicle, furniture...). |
| Custody | عهدة | The handover/assignment of an asset to an employee or supervisor; history is immutable. |
| Notification | إشعار | A system alert required by MVP modules (e.g. contract expiry). |
| Audit trail | سجل التدقيق | Immutable record of who did what, when, from where, and in which tenant. |
| Role | دور | A named, dynamic set of permissions assigned to users. |
| Permission | صلاحية | The right to perform a specific action, named `module.action`. |
| Dashboard | لوحة التحكم | The administrative dashboard with tenant-scoped, permission-aware indicators. |

## Rules

- New domain terms must be added here before they are used in code or UI.
- Arabic terms shown here are the reference UI wording unless the business specifies otherwise (TBD per module).
