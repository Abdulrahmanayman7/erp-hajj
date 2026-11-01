# S04 Phase V — Owner Decisions (V-D1–V-D8)

> **Status:** `APPROVED — OWNER DECISIONS` (documentation)  
> **Decision authority:** Project Owner  
> **Decision date:** 2026-11-01  
> **Design pack:** [PHASE_V_DETAILED_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_V_DETAILED_DESIGN.md)  
> **Register:** [PHASE_V_OWNER_DECISION_REGISTER.md](../09-modules/19-suppliers-and-procurement/PHASE_V_OWNER_DECISION_REGISTER.md)  
> **Does not:** grant final detailed design acceptance · authorize coding, migrations, seeding, commit/push/merge/deploy · authorize S05  
> **Does not:** reopen ADR-0022 **101–130**, Phase I–IV acceptance, or Phase IV `fr_count=2` follow-up

---

## 1. Context

Project Owner approved **V-D1 through V-D8** (2026-11-01) to freeze Phase V product choices for Demand Groups (**123**) and Control Reports (**130**), including **mandatory CSV export for all four reports (V-D8=A)**.

**Distinction:**

| Act | Status |
|---|---|
| Owner decisions V-D1…V-D8 | **APPROVED** (this document) |
| Phase V detailed design acceptance | **PENDING** (separate Owner act) |
| Phase V coding / Fast Track | **NOT AUTHORIZED** |
| S05 / commit / push / merge / deploy | **NOT AUTHORIZED** |

Earlier register **recommendations** are superseded where they differ (notably prior V-D8=C defer-export recommendation → **Owner chose A**).

---

## 2. Approved decisions

| ID | Choice | Decision text |
|---|---|---|
| **V-D1** | **A** | Membership at **Purchase Request header** level (not lines). At most **one active** Demand Group membership per PR. Prevent duplicate planning aggregation. Preserve historical membership. MariaDB 10.4–compatible concurrency-safe uniqueness required. |
| **V-D2** | **B** | **Season optional**; **OU optional**. Administrative and seasonal PRs may be consolidated. Clear scope visibility and **warnings** for mixed-season / mixed-OU membership. **No** new OU-level row ACL beyond Phase III (**III-D5**). |
| **V-D3** | **A** | Lifecycle: **`draft → active → frozen → closed`**. Authorized transitions, editability, validation, audit, and terminal behavior required in design. |
| **V-D4** | **A** | On approved PR cancel (`cancel_approved`): **auto-unlink** from Demand Group; set `unlinked_at` (or approved equivalent); preserve history; exclude from current planning totals; **preserve** Phase IV cancel authorization and engagement-lock rules; design transactional consistency, idempotency, concurrency. |
| **V-D5** | **A** | When any member PR is procurement-engaged: **freeze membership changes** (no add/remove while restriction applies). Define behavior for already frozen/closed groups. Phase V **must not write** `procurement_engaged_at` or other S05 engagement fields. No S05 procurement actions. |
| **V-D6** | **A** | Phase V **must** include all four reports: **RPT-V01** latency, **RPT-V02** overdue/delays, **RPT-V03** outcomes, **RPT-V04** compliance — RPT-V04 **mandatory**. |
| **V-D7** | **B** | Mutations require `consolidate_planning`. Read allowed for appropriately authorized planning users **or** users with `purchase_requests.view`, subject to the existing PR visibility contract. No leak of hidden PR info via headers/totals/counts/APIs/exports. Define safe partial-visibility behavior. No implicit sensitive grants. |
| **V-D8** | **A** | **CSV export REQUIRED** for **all four** control reports (overrides prior defer recommendation). Same filters/visibility as on-screen; explicit export authorization + audit; sensitive-data safety; formula-injection protection; UTF-8 Arabic compatibility; stable columns; pagination/streaming; size/resource limits; empty/error behavior. **No XLSX** unless separately authorized. |

---

## 3. Traceability

| Decision | ADR-0022 / prior contracts |
|---|---|
| V-D1, V-D2, V-D4 | **123** planning-only consolidation; approved needs; PR independence |
| V-D2 | **118** seasonal vs administrative; III-D5 no OU row ACL |
| V-D3, V-D5 | Planning lifecycle; S05 engagement boundary (no S05 in S04) |
| V-D4 | Phase IV `cancel_approved` + engagement lock (accepted) |
| V-D6, V-D8 | **130** reports; export is Owner Phase V product choice |
| V-D7 | PERMISSIONS.md Phase V verbs; Phase III PR `view` contract |

---

## 4. Implementation consequences (high level)

- Schema must enforce exclusive **active** PR membership under MariaDB 10.4.  
- APIs must expose four report GETs **plus** four CSV exports.  
- UI.md “Export DEFERRED” is **superseded** by V-D8=A.  
- Phase IV cancel path gains a Demand Group unlink side-effect without changing cancel authorization.  
- Phase V never writes engagement fields.  
- Coding still requires **separate** Owner design acceptance + coding authorization.

---

## 5. Explicit non-authorizations

- Final Phase V detailed design acceptance  
- Phase V / S05 implementation  
- Migrations, DB writes, permission grants, deploy  
- Git commit / push / merge  
- XLSX export  

---

## 6. Next Owner acts

1. Review engineering-closed detailed design → **FINAL PHASE V DESIGN ACCEPTANCE** (or punch-list).  
2. Authorize Phase V Fast Track coding (V-WP01–V-WP07) separately.  
3. Release acts remain separate.
