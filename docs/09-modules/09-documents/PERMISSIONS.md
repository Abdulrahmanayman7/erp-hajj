# Documents — Permissions

> **Status:** Approved
> **Last updated:** 2026-08-06

| Permission | Purpose |
|---|---|
| `documents.view` | List/view document metadata |
| `documents.upload` | Upload documents (audited) |
| `documents.download` | Download documents (sensitive downloads audited) |
| `documents.update` | Update metadata/links |
| `documents.delete` | Archive/soft delete (audited) |
| `documents.manage_categories` | Manage document categories |

## Rules

- Confidentiality level adds a second gate on top of permissions (semantics TBD).
- Linked-entity access may further constrain visibility (e.g. employee attachments + sensitive data permission): interaction rules TBD.
