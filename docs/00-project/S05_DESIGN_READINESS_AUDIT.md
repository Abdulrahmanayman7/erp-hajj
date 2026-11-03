# S05 — Design Readiness Audit (Planning Only)

> **Audit date:** 2026-11-02  
> **Mode:** Documentation + read-only code inspection  
> **Verified baseline:** `develop` @ `2d128a786639ccd1e3da292629088b7110f347cc` (`2d128a7`)  
> **S04 precondition:** [S04-FINAL-FUNCTIONAL-CLOSURE-2026-11-02.md](../10-decisions/S04-FINAL-FUNCTIONAL-CLOSURE-2026-11-02.md) — **FINAL FUNCTIONAL CLOSURE GRANTED**  
> **Authorizes:** requirements analysis · engineering design proposals · Owner decision framing  
> **Does not:** authorize S05 application code · migrations · seeders · DB writes · deploy · Git commit/push/merge/branch switch · invent finance/accounting schemas

---

## 1. Executive verdict

| Dimension | Classification |
|---|---|
| S04 functional prerequisite | **MET** (Owner FINAL FUNCTIONAL CLOSURE 2026-11-02) |
| S05 product membership | **In Phase 2** (ADR-0020 §3) |
| S05 implementation in codebase | **NOT_STARTED (~0%)** — no RFQ/PO/GRN modules, routes, or permissions |
| S05 module docs (`docs/09-modules/` pack for RFQ–cycle) | **PART 0 COMPLETE** (2026-11-02) — [PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md](../09-modules/19-suppliers-and-procurement/PHASE_S05_PROCUREMENT_CYCLE_DESIGN.md) + §S05 in module core docs + [S05_IMPLEMENTATION_PLAN.md](../09-modules/19-suppliers-and-procurement/S05_IMPLEMENTATION_PLAN.md) |
| S05 functional ADR / engineering ADR | **Not a separate ADR file** — design pack inside module 19 binds to ADR-0020 §S05; ADR-0022/0023 remain S04 parents |
| Design readiness to **start Owner decision freeze** | **Superseded for Part 0** — cycle locked to Owner-stated flow (approved PR → … → close); no new product decisions requested in Part 0 |
| Design readiness to **start coding (Part 1)** | **READY FOR OWNER CODING AUTHORIZATION** — still **NO-GO to code** until explicit Owner Part 1+ authorization |

---

## 2. Official S05 product scope (approved — not invented)

### ADR-0020 §3 — S05

- RFQ  
- Supplier offers and comparison  
- Supplier selection  
- Purchase orders (PO)  
- Receiving and cycle close  

### PHASE2_SCOPE_MATRIX — S05 row

| Field | Text |
|---|---|
| Functional requirements | RFQ; offers/comparison; selection; PO; receiving; cycle close |
| Dependencies | S04; inventory/warehouse touchpoints **TBD in design** |
| Status (pre-this audit) | NOT_STARTED (~0%) |

### ADR-0022 §1 sprint separation (binding)

- **S04:** registry, evaluation, PR, multi-level approvals  
- **S05 only:** RFQ; offers/comparison; selection; PO; receiving; cycle close  
- No payments / executive accounting in S04; same exclusion assumed for S05 until Owner expands via CR  

### Explicit exclusions (Phase 3 / deferred unless CR)

Full finance/accounting, payments, e-invoice, supplier self-service portal, pilgrim apps, AI — ADR-0020 §4 / OUT_OF_SCOPE / module 19 BUSINESS_RULES out-of-scope list.

---

## 3. Verified S04 capabilities reusable by S05

Inspected under baseline `2d128a7`:

| Capability | Location (evidence) | Classification for S05 |
|---|---|---|
| Module `SuppliersAndProcurement` | `backend/app/Modules/SuppliersAndProcurement/` (models, actions, policies, 19 controllers) | **REUSABLE** foundation |
| Supplier master + categories + coverage + lifecycle | Models `Supplier*`; routes `/api/v1/suppliers…` | **REUSABLE** (eligible suppliers for RFQ/PO) |
| Eligibility / documents / banking / evaluations | Phase II streams | **REUSABLE** gates for “qualified to invite / award” |
| Purchase requests + lines + versions + financial review | `PurchaseRequest*` | **REUSABLE** as upstream demand for RFQ |
| Multi-level approval engine | matrices, steps, SoD, delegations, SLA | **REUSABLE** pattern; **not** auto-reuse for PO without Owner decision |
| Demand groups (planning only) | `PurchaseDemandGroup*`; FE banners forbid RFQ/PO | **PARTIALLY REUSABLE** — planning input; must not silently become RFQ merge without Owner rule |
| Engagement lock columns | `purchase_requests.procurement_engaged_at`, `procurement_engagement_ref` | **REUSABLE hooks** — S05 must define writers + semantics |
| Cancel guards reading engagement | `CancelApprovedPurchaseRequest`, `CancelPurchaseRequest`, `PurchaseDemandGroupEngagementGuard` | **REUSABLE** — preserve fail-closed |
| Permissions catalog (suppliers / purchase_requests) | `PermissionCatalog.php` | **REUSABLE** — S05 needs **new** `module.action` names |
| FE modules | `frontend/src/modules/suppliers`, `purchase-requests` (~23 Vue files) | **REUSABLE** shell/patterns; S05 pages **MISSING** |
| Inventory `ReceiveStock` ledger | `Modules/Inventory` + ADR-0011 | **PARTIALLY REUSABLE** — no PO FK today |
| Tenancy / audit / Documents / Season Context | Core + SeasonPilgrims | **REUSABLE** constraints |
| RFQ / Offer / PO / GRN entities | — | **MISSING** |
| S05 permissions / routes / migrations | — | **MISSING** |

**Regression note:** Demand-group UI explicitly states planning-only / no RFQ (`purchaseDemandGroups.noRfqsBanner` in `frontend/src/locales/ar.ts`).

---

## 4. Integration boundaries (design constraints)

| Boundary | S04 stance (locked) | S05 design implication |
|---|---|---|
| Contracts MVP | Not a supplier substitute; optional future `supplier_id` needs separate CR | Do not overload Contracts as PO |
| Inventory | No receive-against-PR; optional `inventory_item_id` on PR lines only | PO receipt vs manual `ReceiveStock` needs Owner decision |
| Seasons | Seasonal PR requires `season_id` | Decide whether RFQ/PO inherit season from PR / demand group / both |
| Documents | Morph + eligibility metadata | Likely attachments on RFQ/offer/PO — design under ADR-0010 |
| Notifications | In-app for PR/SLA/eval | Extend for RFQ deadlines / award / receipt — no new channel without ADR |
| Audit | AuthorizationSecurity + domain transitions | Every award/PO issue/receipt/close must audit |
| Finance | No reservation/payment | Keep out unless CR |
| Tenancy | Fail-closed; cross-tenant 404 | Mandatory Pest matrix for every new aggregate |

---

## 5. Missing S05 functionality (checklist)

1. RFQ aggregate (header/lines, linkage to approved PR and/or demand group)  
2. Supplier invitation / quotation (offer) capture  
3. Offer comparison workspace + selection/award record  
4. Purchase order (PO) lifecycle + lines  
5. Goods receipt (GRN) against PO (partial/full rules TBD)  
6. Procurement cycle close semantics  
7. Writers for `procurement_engaged_*` (+ freeze demand-group membership)  
8. Permission set for procurement cycle  
9. API Resources / Form Requests / Policies  
10. FE navigation group + RTL pages  
11. Module documentation pack (MODULE_TEMPLATE)  
12. Functional ADR + engineering ADR/CR for S05  
13. Inventory linkage design (FK / movement type / dual-path)  
14. TEST_PLAN including cross-tenant and concurrency where locking applies  

---

## 6. Required Owner decisions (numbered)

Labels: **APPROVED REQUIREMENT** (from ADR-0020/0022) vs **PROPOSED ENGINEERING DESIGN** vs **OWNER DECISION REQUIRED**.

### S05-D01 — Upstream source for RFQ
- **Business question (AR):** هل يُنشأ طلب عروض الأسعار (RFQ) من طلب شراء معتمد فقط، أم من مجموعة تخطيط طلبات، أم من كليهما؟  
- **Why:** Demand groups are planning-only in S04; ADR-0022 §123 forbids consolidated RFQ/PO **in S04**.  
- **A:** Approved PR only (1:1 or 1:N lines).  
- **B:** Demand group only (after freeze).  
- **C:** Either, with explicit linkage rules.  
- **Recommended:** **A** for first slice; optional **C** later.  
- **Blocks coding?** Yes for schema.

### S05-D02 — Season binding on RFQ/PO
- **AR:** هل ربط الموسم إلزامي على RFQ/PO عندما يكون الطلب موسمياً؟  
- **A:** Inherit mandatory season from seasonal PR.  
- **B:** Optional season always.  
- **C:** RFQ/PO always require active Season Context.  
- **Recommended:** **A**.  
- **Blocks?** Yes for validation middleware.

### S05-D03 — When to set `procurement_engaged_*`
- **AR:** متى يُقفل طلب الشراء المعتمد ضد الإلغاء (`procurement_engaged_at`)؟  
- **A:** At first RFQ publish.  
- **B:** At supplier selection/award.  
- **C:** At PO issue.  
- **Recommended:** **A** (earliest commitment signal) with clear unlock rules if RFQ cancelled before award.  
- **Blocks?** Yes — guards already exist in S04.

### S05-D04 — Offer comparison method
- **AR:** هل المقارنة يدوية في الواجهة مع تسجيل سبب الاختيار، أم تقييم موزون إلزامي؟  
- **A:** Manual comparison + mandatory selection rationale.  
- **B:** Weighted scorecard (reuse evaluation criteria packs?).  
- **C:** Single-offer award allowed without comparison UI when only one offer.  
- **Recommended:** **A** + allow **C** as exception with audit.  
- **Blocks?** Partially (Offer entity shape).

### S05-D05 — PO approval vs reuse of PR approval engine
- **AR:** هل أمر الشراء يحتاج مسار اعتماد مستقل أم يكفي اعتماد طلب الشراء السابق؟  
- **A:** PR approval sufficient; PO issue is a procurement permission.  
- **B:** Separate lightweight PO approval.  
- **C:** Full reuse of purchase approval matrices for PO value bands.  
- **Recommended:** **A** for MVP S05; escalate to **B** if Owner requires dual control.  
- **Blocks?** Yes for permissions/workflow.

### S05-D06 — Inventory posting on goods receipt
- **AR:** هل استلام أمر الشراء يرحّل تلقائياً إلى دفتر المخزون؟  
- **A:** Atomic post to `inventory_movements` with PO/GRN reference (requires Inventory CR).  
- **B:** Operational GRN only; warehouse posts via existing `ReceiveStock` manually.  
- **C:** Post only for lines linked to `inventory_item_id`.  
- **Recommended:** **C** as pragmatic bridge; document **A** as follow-up hardening.  
- **Blocks?** Yes for receiving DoD.

### S05-D07 — Partial receipt and cycle close
- **AR:** ما شروط إغلاق دورة الشراء عند الاستلام الجزئي؟  
- **A:** Close only when fully received.  
- **B:** Close with explicit cancel/short-close of remainder + reason.  
- **C:** Accounting close (out of Phase 2).  
- **Recommended:** **B**.  
- **Blocks?** Yes for terminal states.

### S05-D08 — Supplier eligibility at invite vs award
- **AR:** هل يُشترط أن يكون المورد Active وeligible عند الدعوة للعرض وعند الترسية؟  
- **A:** Both invite and award.  
- **B:** Award only.  
- **C:** Invite any Active; award requires eligibility re-check.  
- **Recommended:** **A** (aligned with S04 use-time recheck spirit).  
- **Blocks?** No if defaulted; recommend freeze.

### S05-D09 — Module packaging
- **AR:** هل تبقى قدرات S05 داخل `SuppliersAndProcurement` أم موديول فرعي/جديد؟  
- **A:** Extend same Laravel module with `ProcurementCycle` namespace.  
- **B:** New module `Procurement` depending on suppliers/PR.  
- **Recommended:** **A** (matches ADR-0023 one-module preference) unless Owner prefers hard BC split.  
- **Blocks?** Soft — engineering convention.

### S05-D10 — Bank / payment data on PO
- **AR:** هل يُعرض أو يُنسخ الحساب البنكي المتحقق على أمر الشراء في S05؟  
- **A:** Reference verified primary account id only (no payments).  
- **B:** No banking fields on PO in S05.  
- **Recommended:** **B** initially; **A** only if operations require remittance advice prep without payment posting.  
- **Blocks?** Soft.

---

## 7. Proposed engineering design phases (planning only — NOT AUTHORIZED TO CODE)

### Phase 0 — Decision freeze & module pack
- Owner closes S05-D01…D08 (minimum).  
- Create `docs/09-modules/20-…` or extend module 19 with S05 section pack per MODULE_TEMPLATE.  
- Draft functional ADR + engineering ADR/CR.  
- **DoD:** TBDs closed or explicitly deferred; Owner design acceptance recorded.

### Phase 1 — RFQ + offers + selection (design → later impl)
- Aggregates, states, permissions, APIs, UI flows, engagement lock writer.  
- Tests: tenancy, SoD where applicable, cancel guards.

### Phase 2 — PO lifecycle
- Issue/amend/cancel rules; link to award; permissions; audit.

### Phase 3 — Receiving + cycle close + inventory touchpoint
- GRN; D06/D07 rules; Inventory CR if posting; regression on S04 locks and Inventory ledger.

### Phase 4 — Hardening
- RTL nav; control reports extensions; concurrency; CI gates; MATRIX update after Owner acceptance.

Each phase still requires **separate Owner implementation authorization** before application code.

---

## 8. Acceptance criteria (for future S05 closure — foreshadow)

Per ADR-0020 §9 + DEFINITION_OF_DONE:

1. Explicit requirements (module docs; blocking TBDs resolved).  
2. Backend complete for accepted slice.  
3. Frontend complete where required.  
4. Permissions + tenant (+ season where decided) isolation verified.  
5. Automated tests pass in agreed environment.  
6. Evidence retained.  
7. Acceptance criteria checked.  
8. Dual developer review (no self-merge).

Plus S05-specific:

- No bypass of S04 engagement / cancel guards.  
- No inventory negative stock.  
- No payment/accounting invention.  
- Cross-tenant attack tests for every new tenant-owned aggregate.

---

## 9. Testing strategy (design-time)

| Layer | Expectation |
|---|---|
| Pest Feature | CRUD/transitions; 401/403/422; cross-tenant 404; engagement lock; eligibility gates |
| Pest concurrency | Award/issue/receive locking where shared counters or balances |
| Vitest | Forms, permission visibility, empty/error states, RTL critical paths |
| CI | Existing `.github/workflows/ci.yml` (Pint, `php artisan test`, Vitest, `npm run build`); extend MySQL job if InnoDB locking critical |
| Regression | S04 suppliers/PR/approvals/demand groups; Inventory receive |

**No tests were executed in this audit session.**

---

## 10. Risks and blockers

| Risk | Severity |
|---|---|
| No S05 MODULE_TEMPLATE / ADR yet | **P0** for coding; **P1** for design freeze |
| Inventory touchpoint TBD (MATRIX) | **P1** |
| Owner decisions S05-D01…D07 open | **P1** |
| Residual S04 `fr_count=2` / MariaDB 1213 may surface under S05 concurrency | **P2** |
| Accidental RFQ UI creep into demand groups | **P2** |
| Overloading Contracts or Payments into S05 | **P1** (scope) |
| S05 implementation without explicit Owner coding auth | **P0** governance |

---

## 11. GO / NO-GO

| Question | Recommendation |
|---|---|
| Proceed with **Part 0 design documentation**? | **DONE** (2026-11-02) |
| Proceed with **S05 Part 1+ application implementation**? | **NO-GO** until explicit Owner coding authorization (do not auto-start) |

**S05 PART 0 STATUS: COMPLETE — DOCUMENTATION ONLY**  
**S05 IMPLEMENTATION STATUS: NOT STARTED — AWAITING OWNER CODING AUTHORIZATION FOR PART 1**
