# S04 Phase II-A — Owner Remediation Decision M1=A

> **Status:** `APPROVED — OWNER REMEDIATION DECISION`
> **Owner decision date:** 2026-11-01
> **Choice:** **M1=A**
> **Does not:** mark II-A Owner-accepted; authorize commit/push/merge/deploy; authorize II-B/II-C/PR/S05.

## 1. Decision (M1=A)

An eligibility requirement set **cannot be approved** unless it contains **at least one mandatory** (`is_mandatory=true`) document requirement.

Binding consequences:

1. Draft configurations may remain incomplete (empty or optional-only).
2. Approval of empty defs or optional-only packs is **rejected** with a stable domain error (`SUPPLIER_ELIGIBILITY_DEFS_EMPTY` / `SUPPLIER_ELIGIBILITY_DEFS_NO_MANDATORY`).
3. Evaluation must **not** treat an empty mandatory-key set as silently eligible (`SUPPLIER_ELIGIBILITY_NO_MANDATORY_REQUIREMENTS`).
4. Optional requirements never substitute for mandatory ones.
5. No platform-wide default requirement catalog and no invented legal-document mandates.

## 2. Related remediations (engineering; authorized with this pass)

| ID | Topic |
|---|---|
| H1 | Backing Document must remain active and linked to the supplier for eligibility |
| M2 | DB uniqueness of current document records (`current_slot`) |
| M3 | Wildcard norm uniqueness (physical `*_norm` + UNIQUE; MySQL/MariaDB BEFORE INSERT/UPDATE triggers after MariaDB Hostinger rejected STORED GENERATED IFNULL) |
| M4 | Approved config immutability (application + residual raw-SQL risk documented) |
| M5 | Multi-editor SoD via additive editors table |

## 3. References

- [PHASE_II_A_ELIGIBILITY_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_II_A_ELIGIBILITY_DESIGN.md)
- Independent acceptance audit (ACCEPTABLE WITH CONDITIONS)
- II-D1 fail-closed activation
