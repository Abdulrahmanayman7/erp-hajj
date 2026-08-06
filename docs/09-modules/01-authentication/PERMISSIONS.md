# Authentication — Permissions

> **Status:** Approved
> **Last updated:** 2026-08-06

Authentication itself is identity, not authorization — no `module.action` permissions gate login/logout.

- Any active user of an active tenant may authenticate.
- After authentication, all access is governed by the permission catalog in [PERMISSION_MODEL.md](../../06-security/PERMISSION_MODEL.md).
- The current-user endpoint returns the user's effective permissions for frontend UX gating (Permission Guard) — backend authorization remains mandatory.
