# Notifications — Test Plan

> **Status:** Draft
> **Last updated:** 2026-08-06

- **Cross-tenant (mandatory):** notifications for tenant A events never reach tenant B users; listing endpoint returns own-tenant, own-user records only.
- Generation: contract nearing expiry produces the notification (time-travel test); stock below minimum produces low-stock alert.
- Queue: generation jobs execute in the correct tenant context.
- API: list/filter own notifications; mark-as-read per decided model.
- Content: no data leakage beyond recipient permissions (per decided rules).
- Mocking: notifications mocked/faked in unrelated module tests.
- Frontend: bell indicator and list rendering (per decided UI).
