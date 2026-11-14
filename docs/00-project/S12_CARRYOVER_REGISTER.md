# S12 — Outstanding Carryover Register

> **Package date:** 2026-11-11  
> **Production:** **NOT_AUTHORIZED**  
> Carryovers remain Phase 2 obligations unless a later Owner/CR decision says otherwise.  
> **G4/G5 must not be relocated to Phase 3.**

| ID | Item | Category | Status | Notes |
|---|---|---|---|---|
| C-S05 | S05 Owner paperwork / acceptance certificate | Owner | **PENDING** | Certificate not located |
| C-S06 | S06 final Owner acceptance | Owner | **PENDING** | Stream = FUNCTIONALLY_ACCEPTED / TECHNICAL_VALIDATION_DEFERRED |
| C-S07 | S07 final Owner acceptance | Owner | **PENDING** | Merge ≠ acceptance |
| C-S08 | S08 documentation reconciliation | Docs | **RESOLVED in S12 docs** (merge evidence) | Status now `OWNER_ACCEPTED / MERGED / NOT_DEPLOYED`; deploy still **not** authorized |
| C-S09 | S09 independent security Approve | Security | **PENDING** | GitHub Approve not recorded |
| C-S10-SEC | S10 human security review #52–#55 | Security | **PENDING** | Open after admin closure |
| C-S10-UAT | S10 Browser UAT | UAT | Covered by Phase 2 Live UAT **PASS** 2026-11-14 (shell) | Not production auth |
| C-S11-LIVE-UAT | S11 live Browser UAT | UAT | Covered by Phase 2 Live UAT **PASS** 2026-11-14 (shell) | Not production auth |
| C-S11-OWNER | S11 Owner administrative closure | Owner | **APPROVED** (2026-11-14) | `CLOSED_WITH_CARRYOVER`; production / Phase 2 final acceptance not granted |
| C-S12-LIVE-UAT | S12 live operational Browser UAT | UAT | **PASS** 2026-11-14 | Attested `erp_hajj_phase2_uat`; [PHASE2_LIVE_UAT_ATTEMPT_2026-11-14.md](PHASE2_LIVE_UAT_ATTEMPT_2026-11-14.md) — production still **NOT_AUTHORIZED** |
| C-S12-OWNER | S12 / Phase 2 handoff Owner acceptance | Owner | **PENDING** | |
| C-G4 | Live GPS provider (G4_LIVE) | Product | **BLOCKED** | See [PHASE2_G4_G5_U03_STATUS.md](PHASE2_G4_G5_U03_STATUS.md) |
| C-G5 | GPS history + legal retention | Product | **BLOCKED** | See [PHASE2_G4_G5_U03_STATUS.md](PHASE2_G4_G5_U03_STATUS.md) |
| C-U03 | Production SLA authorization | Product/Ops | **BLOCKED** | Runbook only; activation unset — [PHASE2_G4_G5_U03_STATUS.md](PHASE2_G4_G5_U03_STATUS.md) |
| C-S04-RES | S04 residual follow-ups | Engineering | Open | Preserved from S04 closure |
| C-MVP-UAT | MVP interactive browser UAT / production deploy | MVP track | Pending in ROADMAP | Distinct from Phase 2 Workflow |

## Phase 3

Phase 3 / OUT_OF_SCOPE items remain **deferred**. They are **not** a substitute for closing the carryovers above.
