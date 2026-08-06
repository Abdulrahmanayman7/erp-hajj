# Users and Authorization — Permissions

> **Status:** Approved
> **Last updated:** 2026-08-06

| Permission | Purpose |
|---|---|
| `users.view` | List/view tenant users |
| `users.create` | Create users |
| `users.update` | Update users |
| `users.disable` | Disable/enable users (audited) |
| `users.delete` | Delete users (audited; policy per deletion rules TBD) |
| `roles.view` | List/view roles |
| `roles.create` | Create roles |
| `roles.update` | Update roles |
| `roles.delete` | Delete roles |
| `roles.assign_permissions` | Change a role's permission set (audited) |

## Rules

- Role/permission changes always produce audit records.
- A user must not be able to escalate their own permissions (self-assignment guard): enforcement detail TBD.
