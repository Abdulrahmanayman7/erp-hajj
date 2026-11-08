# S09-D02 — Worked Examples (aligned to Owner approval 2026-11-08)

> All examples: **`ILLUSTRATIVE — NOT PRODUCTION DATA`**  
> Formulas match `OWNER_APPROVED` choices Q1–Q17; display of ratios uses **temporary** Q18 assumptions only.

---

## Transport

**T01** — Season S has movements: 2 draft, 5 planned, 3 in_progress, 4 completed, 1 cancelled.  
Included (Q1=A)=planned+in_progress → **8**. Excluded draft/completed/cancelled.

**T02** — Same fixture → completed = **4**.

**T08** — Breakdowns: 2 open, 1 replaced, 1 closed, 1 cancelled.  
Q2=B all≠cancelled → **4**. (Not open-only=2.)

---

## Accommodation

**A02** — Assignments with booking confirmed/completed: 10 overlap “now”, 2 draft, 1 cancelled, 3 completed with `effective_ends_at <= now`.  
Snapshot now (Q4=A) + assignment count (Q3=A) → **10**. Past completed outside window excluded.

**A03** — Of 10 confirmed overlapping now: 4 ops null/assigned, 3 arrived, 2 checked_in, 1 checked_out.  
Q5=A arrived+checked_in → **5**. Assigned/null **not** occupied.

---

## Procurement

**P01** — 12 PRs with season_id=S (incl. 2 cancelled); 3 PRs season null.  
Q17=A + Q6=A → **10** (null excluded; cancelled excluded).

**P02** — 4 approved among in-season non-null → **4**.

**P03** — RFQs in season: 1 draft, 2 published, 1 awarded, 1 cancelled → Q7=A → **3**.

**P04** — 6 POs season S incl. 1 cancelled → Q6=A → **5**.

**P05** — Statuses: 1 draft, 2 issued, 1 partially_received, 1 received, 1 closed, 1 cancelled → issued pipeline → **5**.

**P06** — received=1 (lines full), closed fully received=1, closed short-close incomplete=1, partial=1.  
Q8=C → fulfilled **2** only (short-close fails line condition).

**P07** — PO lines `quantity_received` sum = 15; posted GRN lines sum = 15. KPI shows **15** once (Q9=A).  
Wrong: 15+15=30.

**P08** — 2 issued + 1 partial with short lines → **3** pending.

---

## Projects

**J01** — 8 projects (1 soft-deleted ignored): 1 draft, 3 active, 2 completed, 2 cancelled → Q11=A exclude cancelled → **6**.

**J02** → **3**. **J03** → **2**.

**J07** — Non-cancelled progress_percent: 0 (draft), 20, 40, 60, 100, 80 → AVG = **50.0** arithmetic (Q12=A). Cancelled excluded. Null progress (if any) excluded from AVG — not coerced to 0.

**J09** — completed=2, eligible active+completed=5 → 2/5=**40.0%** (temp 1 d.p.).  
Zero denom: 0 eligible → **N/A** (temp assumption; final → D06/D14).

---

## Risks & Complaints

**R01** — Risks: 5 open-ish + 2 closed → open **5**.

**R02** — Among open, severity high=2 critical=1 medium=2; plus 1 closed critical → Q16=A → **3** (closed excluded).

**R03** — Closed mitigated=2, accepted=1 → mitigated **2**.

**R04** — Complaints: new=3, under_review=2, in_progress=1, resolved=4, closed=2 → Q14=A → **6**.

**R05** — Q15=A resolved-only → **4** (closed not included).

**R09** — escalation_level>0 → **3** (illustrative).

---

## Ratio edge (J09) — temporary presentation only

| Case | Temp display (Q18 assumption) | Final policy |
|---|---|---|
| 0 completed / 0 eligible | N/A | D06/D14 |
| 0 completed / 5 eligible | 0.0% | D06/D14 |
| 1 / 3 | 33.3% (1 d.p.) | D06/D14 |
