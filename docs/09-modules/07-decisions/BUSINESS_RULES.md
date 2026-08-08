# Decisions — Business Rules

> **Status:** Approved
> **Last updated:** 2026-08-06

- A decision is a **separate business entity** — never merged into tasks.
- A decision may originate from a meeting **or exist independently** (originating meeting is optional).
- A decision may generate one or more tasks.
- **Completing one task must not automatically close the decision unless all required tasks are completed.**
- Statuses (suggested): **New, Approved, In Progress, Completed, Blocked, Cancelled.**
- Decision approval and closure are audited events.
- Approval requires `decisions.approve`; approval details not explicitly defined are **TBD**.

## TBD

- Approval flow details (single approver vs. chain; who may approve by default): **TBD** — explicitly not defined.
- Decision number generation: TBD.
- Whether "required tasks" is a flag on task linkage or all linked tasks are required: TBD.
- Blocked status semantics (who sets it, does it propagate from blocked tasks): TBD.
- Priorities list (uses High/Medium/Low like tasks?): field exists; values TBD.
