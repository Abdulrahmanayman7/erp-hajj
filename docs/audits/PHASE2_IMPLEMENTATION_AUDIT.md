# تدقيق تنفيذ المرحلة الثانية — ERP Hajj (Phase 2 Implementation Audit)

> **نوع المهمة:** تدقيق قراءة فقط (READ-ONLY) — لا تشغيل Migrations / Seeders / اختبارات على قاعدة حقيقية في هذه الجلسة.  
> **تاريخ التدقيق:** 2026-10-29  
> **الفرع:** `develop`  
> **Commit الأساسي المعتمد للأدلة:** `ae51644f11d9a6ef6c6d6d24b332d135d93e2952` (`ae51644` — 2026-10-27 — `style: fix Laravel Pint formatting`)  
> **حالة Working Tree:** غير نظيفة (~61 مسارًا متغيرًا وقت بدء التدقيق) — انظر §2 و§10.

---

## 1. Executive Summary

المرحلة الثانية في المستودع **غير مكتملة** كمرحلة تشغيلية شاملة بحسب Workflow المرفق (`S01`–`S12`). ما يظهر بأدلة كود واضحة هو:

| المجال | الحكم الموجز |
|---|---|
| أساس المواسم والحجاج (SeasonPilgrims) | **BACKEND_ONLY** — Backend + Pest موجودان؛ لا Frontend |
| النقل والأسطول (Transportation) | **BACKEND_ONLY** / جزئيًا أقرب لـ **COMPLETE** على مستوى Backend لـ S01–S02؛ لا Frontend؛ لا GPS |
| الإسكان والمخيمات (Accommodation) | في **HEAD**: Backend + Frontend + مراجعات قبول موثّقة → **IMPLEMENTED_UNVERIFIED** (لم تُعاد تشغيل الاختبارات في هذا التدقيق). في **Working Tree الحالي**: إزالة غير مُلتزَمة لـ Routes / Provider / Permissions / Router → **BLOCKED / انحدار حرج** |
| المشتريات / الموردون / المشاريع المتقدمة / المخاطر / الشكاوى / القوالب الموسمية / KPIs التشغيلية / لوحة قيادة تنفيذية حية | **NOT_STARTED** في الكود (ومعظمها خارج نطاق Change Request المعتمد داخل المستودع) |

**تعارض حاكمية مهم:** وثيقة Workflow المرفقة تُدرج S04–S12 (مشتريات، مشاريع، مخاطر، KPIs، UAT…) داخل المرحلة الثانية، بينما `docs/00-project/OUT_OF_SCOPE.md` و`CHANGE_REQUEST_PHASE2_FOUNDATION.md` يصرّحان أن المشتريات والقوالب الموسمية الكاملة والشكاوى وGPS وغيرها **خارج** Phase 2 المعتمد في المستودع. التدقيق يسجّل التعارض ولا يختار نطاقًا بصمت.

**نسبة إنجاز تقريبية للمرحلة الثانية بحسب Workflow S01–S12:** ≈ **28–35%** (منهجية في §13).  
**نسبة إنجاز تقريبية لنطاق المستودع المعتمد فقط (Foundation + Transport + Accommodation):** ≈ **55–65%** مع تحفظات Frontend للنقل/المواسم وانحدار Working Tree للإسكان.

---

## 2. Project Snapshot

| البند | القيمة |
|---|---|
| Branch | `develop` |
| HEAD | `ae51644` (2026-10-27) |
| رسالة HEAD | `style: fix Laravel Pint formatting` |
| Commits Phase 2 ذات الصلة (عيّنة) | `736c776` seasons+transport · `19ab869` accommodation · `99dc292` accommodation/localization/performance |
| Working Tree | متسخ: تعديلات على `PermissionCatalog.php` و`bootstrap/providers.php` و`routes/api.php` وملفات نقل/توثيق؛ **إزالة** تسجيل Accommodation من الـProvider والـRoutes والـCatalog مقارنةً بـ HEAD |
| هذا التدقيق نفّذ | قراءة كود، docs، routes، migrations، tests، git history/diff — **لم** يشغّل Pest/Vitest/migrate |

### ملاحظة منهجية عن HEAD مقابل Working Tree

- الأدلة «ما هو منفَّذ ومُدمَج في آخر commit» تعتمد **HEAD**.
- الأدلة «ما سيشغّل المطوّر الآن من القرص» يجب أن تُراعي أن Working Tree **حذف** ربط Accommodation التشغيلي (permissions / routes / provider) دون commit — وهذا يُصنَّف **CRITICAL** حتى يُستعاد أو يُوثَّق عمدًا.

---

## 3. Reference Documents & Missing References

| المرجع المطلوب | الحالة | المصدر |
|---|---|---|
| فكرة منصتي (المتطلبات الوظيفية) | **موجود كمرفق Cursor** | `attachments/.../__________.pdf` (منصة قيادة وإدارة حملات الحج) — **غير موجود كملف داخل شجرة `docs/`** |
| الخطة التنفيذية | **غير موجود** في المستودع ولا في المرفقات المتاحة لهذه الجلسة | يجب جلبه صراحةً — لا افتراض لمحتواه |
| `ERP_Hajj_Phase_2_Workflow_Reviewed.pdf` / Workflow المتابعة | **موجود كمرفق** | `attachments/.../Hajj_ERP__________________________________________.pdf` (Oct 28, 2026 · إصدار 1.0) — **غير موجود داخل `docs/`** |
| MVP / المرحلة الأولى | موجود | `docs/00-project/MVP_SCOPE.md`, `ROADMAP.md`, `DEFINITION_OF_DONE.md`, وحدات `docs/09-modules/00`–`15` |
| Phase 2 داخل المستودع | موجود | `CHANGE_REQUEST_PHASE2_FOUNDATION.md`, `ADR-0017`, `ADR-0018`, وحدات 16–18, `docs/reviews/ACCOMMODATION_*` |
| ADR-0019 Accommodation | **PROPOSED / NOT ACCEPTED** | `docs/10-decisions/ADR-0019-ACCOMMODATION-CAMPS-DRAFT.md` |

### فرق تقسيم السبرينتات (مهم)

| المصدر | التقسيم |
|---|---|
| **Workflow المرفق (معتمد لهذا التدقيق لعناوين S01–S12)** | S01 أساس النقل · S02 الرحلات/التفويج · S03 الإسكان · S04 موردون/طلبات شراء · S05 دورة شراء · S06 مشاريع · S07 مخاطر/شكاوى · S08 قوالب موسمية · S09 KPIs · S10 لوحة تنفيذية · S11 تكامل/اختبارات · S12 UAT |
| جدول مقترح في نص طلب التدقيق | Sprint 1 = مواسم/حجاج، ثم نقل، إسكان… |
| نطاق Change Request داخل المستودع | Foundation مواسم/حجاج + نقل + إسكان فقط؛ الباقي غالبًا `OUT_OF_SCOPE` |

إدارة المواسم والحجاج في Workflow تُعامل كـ**اعتماد Foundation** وليس S01 مستقلًا؛ التدقيق يقيّمها كـ **Prerequisite / Foundation** بالإضافة إلى مصفوفة المجالات.

---

## 4. Phase 2 Scope Matrix

| المجال | داخل Workflow Phase 2؟ | داخل CR المستودع؟ | أدلة كود (HEAD) | الحكم |
|---|---|---|---|---|
| A. مواسم وحجاج | اعتماد تشغيلي | نعم | `backend/app/Modules/SeasonPilgrims/` (~124 ملف) · migrations `2026_10_05_100*` · tests Feature · permissions في Catalog | **BACKEND_ONLY** |
| B. نقل وأسطول | S01–S02 | نعم | `Transportation/` (~140) · migrations `2000*` · concurrency workers · لا GPS في الموديول | **BACKEND_ONLY** (S01–S02 Backend قوي) |
| C. إسكان ومخيمات | S03 | نعم | `Accommodation/` (~75) + FE `frontend/src/modules/accommodation/` (32) + reviews قبول · **ADR-0019 غير مقبول** | **IMPLEMENTED_UNVERIFIED** على HEAD؛ **BLOCKED** على Working Tree الحالي |
| D. مشتريات/موردون | S04–S05 | لا (OUT_OF_SCOPE) | لا Module / لا migrations / لا routes | **NOT_STARTED** |
| E. مشاريع (متقدمة) | S06 | لا (مشاريع متقدمة خارج؛ Tasks MVP موجودة) | لا Module مشاريع Phase 2؛ يوجد `Tasks` من MVP | **NOT_STARTED** (Phase 2) / MVP Tasks منفصلة |
| F. مخاطر/شكاوى | S07 | شكاوى خارج النطاق؛ مخاطر متقدمة خارج | لا أدلة | **NOT_STARTED** |
| G. قوالب موسمية | S08 | خارج (full seasonal cloning) | لا أدلة | **NOT_STARTED** |
| H. KPIs تشغيلية Phase 2 | S09 | Advanced KPI engine خارج | Dashboard MVP فقط (ADR-0014) | **NOT_STARTED** لـ Phase 2 |
| I. لوحة تنفيذية حية/خرائط | S10 | GPS/command خارج | Dashboard إداري MVP بدون نقل/إسكان widgets | **PARTIAL** كـ MVP فقط؛ Phase 2 **NOT_STARTED** |

---

## 5. Sprint-by-Sprint Assessment (Workflow S01–S12)

### جدول إلزامي

| Sprint | Status | Completion Estimate | Evidence | Missing Work | Blockers | Ready to Close |
|---|---|---|---|---|---|---|
| **S01** أساس النقل والأسطول | **PARTIAL** → Backend قوي | **~75%** (Backend) / **~40%** (DoD كامل مع UI) | Module Transportation · registry/fleet/points/drivers · Pest registry/fleet/tenancy · permissions `transportation.*` · ADR-0018 | Frontend transportation · grants أدوار غير `tenant_owner` · تقارير إشغال منفصلة | لا Frontend؛ دور المصفوفة TBD | **لا** |
| **S02** الرحلات والتفويج | **PARTIAL** → Backend قوي | **~80%** (Backend) / **~45%** (مع UI) | Movements/Runs/passengers/assignments/timeline/reviews · atomic add · history entitlement · `TransportConcurrencyTest.php` | UI تشغيل ميداني · تقارير · تحقق تشغيل اختبارات MySQL في هذه الجلسة **NOT VERIFIED** | لا UI؛ Working Tree قد يحوي hardening غير ملتزم بالكامل | **لا** |
| **S03** الإسكان والمخيمات | **IMPLEMENTED_UNVERIFIED** (HEAD) / **BLOCKED** (WT) | **~70%** على HEAD شريحة Sprints 1–3 الموثقة · **~20%** على WT الحالي | Code + FE + `docs/reviews/ACCOMMODATION_SPRINT{1,2,3}_ACCEPTANCE.md` (MariaDB/Vitest تاريخيًا) | قبول ADR-0019 · ربط `SeasonPilgrimCancelled` (مذكور out of scope في المراجعات) · Browser E2E ناقص في المراجعات · استعادة wiring في WT | ADR غير مقبول · انحدار WT يفصل Routes/Permissions/Provider/Router | **لا** |
| **S04** موردون وطلبات شراء | **NOT_STARTED** | **~0%** | لا Module | كل النطاق | تعارض نطاق Workflow vs OUT_OF_SCOPE | **لا** |
| **S05** دورة شراء/تعاقد/استلام | **NOT_STARTED** | **~0%** | لا Module (عقود MVP قانونية فقط) | RFQ/PO/استلام تشغيلي | خارج CR المستودع | **لا** |
| **S06** مشاريع ومهام موسمية | **NOT_STARTED** (Phase 2) | **~5%** إن احتُسب Tasks MVP كاعتماد | `Modules/Tasks` MVP | مشاريع بميزانية/معالم مرتبطة بالموسم | خارج CR | **لا** |
| **S07** مخاطر وشكاوى | **NOT_STARTED** | **~0%** | لا أدلة | السجل كامل | خارج CR (شكاوى صراحةً) | **لا** |
| **S08** قوالب موسمية | **NOT_STARTED** | **~0%** | ممنوع في OUT_OF_SCOPE كـ full cloning | قواعد النسخ الآمن | يحتاج Change Request | **لا** |
| **S09** مؤشرات أداء | **NOT_STARTED** | **~5%** (Dashboard MVP counts فقط) | `Modules/Dashboard` MVP | KPIs نقل/إسكان/مشتريات… | Advanced KPI خارج النطاق | **لا** |
| **S10** لوحة قيادة تنفيذية | **NOT_STARTED** لـ Phase 2 | **~10%** (لوحة MVP) | Dashboard إداري؛ صراحةً بدون GPS/نقل/إسكان في README | خرائط حية، تنبيهات موسم، إشغال | GPS مشروط بمصدر بيانات | **لا** |
| **S11** تكامل واختبارات قبول | **PARTIAL** | **~25%** | CI فيه job MySQL Transportation؛ مراجعات Accommodation؛ لا تقرير إغلاق Phase 2 موحّد | مصفوفة تكامل عبر الموديولات · اختبارات عربية/RTL شاملة Phase 2 | انحدار WT · عدم إعادة تشغيل الاختبارات هنا | **لا** |
| **S12** UAT وتسليم | **NOT_STARTED** | **~0%** | لا محضر UAT Phase 2 | سيناريوهات مستخدمين · قبول مالك | يعتمد إغلاق S01–S11 حسب النطاق | **لا** |

### Foundation (خارج ترقيم S01 في Workflow لكنه شرط)

| البند | Status | Estimate | Ready to Close |
|---|---|---|---|
| Season + Pilgrim foundation | **BACKEND_ONLY** | Backend ~85% · منتج كامل مع UI ~45% | **لا** — يحتاج Frontend staff + قرار شاشة الحجاج المستقلة (سؤال Workflow #1 و#مصفوفة الحجاج) |

---

## 6. Detailed Feature Gap Analysis

### A. إدارة المواسم والحجاج

**مثبت:**

- Backend module: `backend/app/Modules/SeasonPilgrims/` (Models/Actions/Controllers/Policies/Season Context).
- Migrations: `2026_10_05_100000` … `100010`.
- Permissions في `PermissionCatalog.php` (`seasons.*`, `pilgrims.*`, `season_pilgrims.*`, …) — موجودة في Working Tree وHEAD.
- Provider مسجّل: `SeasonPilgrimsServiceProvider` في `bootstrap/providers.php`.
- Feature tests تحت `backend/tests/Feature/SeasonPilgrims/` (ملفات متعددة: Season/Pilgrim/Groups/Tenancy/Context/Schema…).
- ربط تشغيلي لاحقًا عبر `SeasonPilgrim` كمرجع للنقل/الإسكان (عقود foundation).

**ناقص:**

- لا يوجد `frontend/src/modules/season-pilgrims/`.
- لا مسارات Router للمواسم/الحجاج في `frontend/src/app/router/index.ts` (Working Tree؛ وHEAD كذلك للنقل/المواسم).
- إدارة حاج كشاشة مستقلة كاملة: Workflow يطلب قرار نطاق (ربط فقط vs شاشة).
- تشغيل نتائج الاختبارات في هذه الجلسة: **NOT VERIFIED**.

**الحكم:** **BACKEND_ONLY**.

### B. النقل والأسطول

**مثبت (HEAD + ملفات Module):**

- نقاط نقل، مركبات، سائقون خارجيون، أسطول موسمي، استحقاقات، حركات، Runs، تعيينات، ركاب، timeline، reviews.
- منع التداخل: `ResourceOverlapGuard` + اختبارات تعيين + `tests/Concurrency/TransportConcurrencyTest.php` + `worker.php`.
- لا أعمدة/منطق GPS داخل `Modules/Transportation` (بحث `gps|latitude` = صفر).
- Docs: `docs/09-modules/17-transportation-and-fleet/*`, ADR-0018.
- Frontend transportation: **غير موجود**.

**ناقص مقابل فكرة منصتي / Workflow:**

- GPS / تتبع حي: **غير منفّذ** (ومستبعد في OUT_OF_SCOPE / حدود ADR-0018).
- Offline / QR: غير منفّذ.
- تقارير إشغال مخصصة كمنتج: غير ظاهرة كموردات تقرير منفصلة (إشغال يظهر كـ `occupied_count` على الـRun).
- UI تشغيل/تفويج.

**الحكم:** **BACKEND_ONLY** لـ S01–S02؛ GPS = **NOT_STARTED** (عن قصد حسب المستودع).

### C. الإسكان والمخيمات

**مثبت على HEAD:**

- Module Backend كامل المسارات تحت `/api/v1/accommodation/...` (حُذفت من Working Tree في diff غير ملتزم).
- 11 صلاحية `accommodation.*` في Catalog على HEAD.
- Frontend صفحات: Overview, Facility, Allocation, Drafts, Scopes + API/queries/mutations + Vitest ملفات.
- Router/Nav على HEAD يشير إلى `/app/accommodation`.
- Migrations `2026_10_15_*` و`2026_10_19_*` و`2026_10_27_*` (أحداث، أقفال حاج، أكواد).
- مراجعات قبول Sprint 1–3 تحت `docs/reviews/` تدّعي PASS على MariaDB + Vitest (أدلة تاريخية؛ **لم تُعد في هذا التدقيق**).

**فجوات موثّقة حتى على HEAD:**

- ADR-0019 **غير مقبول**.
- README الوحدة 18 ما زال يقول «Not implemented» (وثائق قديمة — تتعارض مع الكود/المراجعات).
- ROADMAP يقول Accommodation «Not started» — **قديم**.
- مستمع `SeasonPilgrimCancelled` للإسكان: غير موجود في Module (مذكور كـ out of scope في مراجعات Sprint 2/3).
- Browser E2E: المراجعات تصرّح بفجوات تحقق تفاعلي.

**انحدار Working Tree (CRITICAL):**

- `git diff HEAD` يُظهر حذف: `AccommodationServiceProvider` من `providers.php`، كل مسارات accommodation من `api.php`، وكل تعريفات `accommodation.*` من `PermissionCatalog.php`.
- Router/Navigation الحاليان بلا مسارات إسكان رغم بقاء ملفات الصفحات.

**الحكم:** على HEAD = **IMPLEMENTED_UNVERIFIED** (شريحة Sprints 1–3 موثّقة)؛ على القرص الحالي = **BLOCKED**.

### D–G. مشتريات، مشاريع Phase 2، مخاطر/شكاوى، قوالب

لا Modules تحت `backend/app/Modules/` لهذه الأسماء، لا migrations مطابقة، لا permissions، لا صفحات Vue.  
**الحكم:** **NOT_STARTED**.  
ملاحظة: عقود/مهام/مخزون MVP موجودة لكنها **لا** تستبدل دورة المشتريات أو إدارة المشاريع الموسمية حسب Workflow.

### H–I. KPIs ولوحة تنفيذية

لوحة `Dashboard` MVP (`dashboard.view`, ADR-0014) — صراحةً خارج نطاق GPS وwidgets النقل/الإسكان في `docs/09-modules/13-dashboard/README.md`.  
لا محرك KPIs للنقل/الإسكان/المشتريات.  
**الحكم:** Phase 2 = **NOT_STARTED**؛ MVP dashboard = منفّذ كاعتماد سابق.

---

## 7. Backend/Frontend Integration Findings

| تكامل | النتيجة |
|---|---|
| SeasonPilgrims API ↔ Frontend | **مقطوع** — لا UI |
| Transportation API ↔ Frontend | **مقطوع** — لا UI |
| Accommodation API ↔ Frontend | على HEAD: مربوط Router/Nav/API client؛ على WT: API+permissions مفصولة وRouter بلا مسارات → **تكامل منكسر محليًا** |
| Transport ↔ Accommodation | لا كتابة متبادلة (مقصود معماريًا ADR-0017/18)؛ لا check-in تلقائي عند وصول النقل |
| Dashboard ↔ Phase 2 domains | لا widgets موسم/نقل/إسكان |
| Contracts `hotel`/`transportation` | فئات قانونية MVP — ليست مخزون غرف/أسطول تشغيلي |

---

## 8. Security & Multi-Tenancy Findings

| الموضوع | الخطورة | الدليل / الملاحظة |
|---|---|---|
| انحدار فصل Routes/Permissions للإسكان في WT | **CRITICAL** | إن شُغّل التطبيق من WT: سياسات تتوقع `accommodation.*` بينما Catalog لا يعرّفها؛ مسارات API غائبة؛ أو العكس عند استعادة جزئية |
| Tenant isolation Foundation/Transport | يفترض مغطى باختبارات Tenancy (وجود ملفات) — **تشغيلها NOT VERIFIED هنا** | `TransportTenancyTest`, `SeasonPilgrimsTenancyTest` |
| Season context | موجود للنقل عبر `season.context` middleware في `api.php` | |
| صلاحيات النقل/المواسم افتراضيًا لـ `tenant_owner` فقط | **MEDIUM** | مصفوفة أدوار أخرى TBD في docs |
| GPS/بيانات موقع | غير منفّذ — لا تسريب موقع حي | |
| Double booking نقل/إسكان | منطق موجود في الكود + concurrency tests ملفات — **نتائج التشغيل الحالية NOT VERIFIED** | |
| N+1 / Pagination | لم يُجرَ profiling في هذا التدقيق | **NOT VERIFIED** |
| Audit | أحداث نقل في `AuthorizationSecurityEvent`؛ إسكان له مسار audit في الموديول على HEAD | اكتمال تغطية كل عملية حرجة: **NOT VERIFIED** بالكامل |

---

## 9. Test Evidence & Verification Gaps

| الدليل | النوع | نتيجة هذا التدقيق |
|---|---|---|
| `backend/tests/Feature/SeasonPilgrims/*` | Test exists | **NOT RUN** |
| `backend/tests/Feature/Transportation/*` | Test exists | **NOT RUN** |
| `backend/tests/Concurrency/TransportConcurrencyTest.php` | Test exists (MySQL-oriented) | **NOT RUN** |
| `backend/tests/Feature/Accommodation/*` + concurrency | Test exists | **NOT RUN** |
| `docs/reviews/ACCOMMODATION_SPRINT3_ACCEPTANCE.md` | Historical manual/CI-like report (60 Pest + 29 Vitest على MariaDB) | **Manually verified سابقًا (موثّق)** — **ليس** إعادة تحقق اليوم |
| Frontend Vitest accommodation | Test exists | **NOT RUN** |
| Browser E2E Phase 2 | — | **غير موجود / غير مكتمل** حسب المراجعات |
| Production verified | — | **لا** |

لا يدّعي هذا التقرير أن أي اختبار «passed» في جلسة التدقيق الحالية.

---

## 10. Critical Blockers

1. **CRITICAL — Working Tree يفك ربط Accommodation** (permissions / `api.php` / `AccommodationServiceProvider` / Router-Nav مقارنةً بـ HEAD).  
2. **CRITICAL — تعارض نطاق Phase 2** بين Workflow المرفق و`OUT_OF_SCOPE`/`CHANGE_REQUEST` داخل المستودع قبل بدء S04+.  
3. **HIGH — لا Frontend للمواسم/الحجاج ولا للنقل** رغم أن DoD في Workflow يشترط UI + API + Tests.  
4. **HIGH — ADR-0019 غير مقبول** بينما التنفيذ والمراجعات تسير على شرائح موافقة محدودة — فجوة حوكمة.  
5. **HIGH — لا محضر UAT/تكامل Phase 2 موحّد** (S11–S12).  
6. **MEDIUM — وثائق ROADMAP/README الوحدة 18 متقادمة** وتضلّل حالة التنفيذ.  
7. **MEDIUM — GPS مطلوب في فكرة منصتي لكن مرفوض/مشروط في المستودع** — يحتاج قرار منتج صريح قبل S10.

---

## 11. Prioritized Remaining Backlog

1. استعادة أو توثيق انحدار Accommodation في Working Tree (إعادة Catalog/Routes/Provider/Router أو commit واعٍ).  
2. قرار حاكمية: اعتماد Workflow S04–S12 أم تقييد Phase 2 بـ CR المستودع.  
3. Frontend SeasonPilgrims (حد أدنى تشغيلي للموسم/المشاركة).  
4. Frontend Transportation (registry + تشغيل رحلات/ركاب).  
5. إغلاق فجوات Accommodation الموثقة (مستمع إلغاء المشاركة، E2E، قبول ADR أو ADR بديل).  
6. فقط بعد القرار: S04+ أو تأجيلها لمرحلة لاحقة رسميًا.  
7. KPIs/لوحة تنفيذية مرتبطة ببيانات النقل/الإسكان الحقيقية (بدون GPS وهمي).  
8. S11 تقرير تكامل + S12 UAT.

---

## 12. Recommended Sprint Execution Order

1. **Stabilization:** إصلاح Working Tree Accommodation + مزامنة docs (ROADMAP/README-18).  
2. **Foundation UI:** مواسم/حجاج (يفتح تشغيل S02/S03 ميدانيًا).  
3. **Transport UI (S01–S02 product close).**  
4. **Accommodation governance close (S03):** ADR قرار + فجوات المراجعات.  
5. **S11 جزئي** على الثلاثي Foundation/Transport/Accommodation.  
6. **إيقاف أو CR صريح** قبل S04–S10 حسب OUT_OF_SCOPE.  
7. **S12** على النطاق المُقرَّر فقط.

---

## 13. Phase 2 Completion Estimate & Methodology

**المنهجية (شفافة — ليست حقيقة مؤكدة):**

- وزن كل Sprint من S01–S12 بالتساوي (≈8.3%).  
- داخل كل Sprint: Backend 40% · API/Permissions/Tenancy 20% · Frontend 25% · Tests/Verification 15%.  
- Foundation (مواسم/حجاج) يُحسب كشرط ويؤثر على جاهزية S02/S03 دون إضافة Sprint رقم 13.

**تقدير مجمّع مقابل Workflow الكامل S01–S12:** ≈ **30% ±5**.  
**تقدير مقابل نطاق المستودع المعتمد فقط:** ≈ **60% ±5** على HEAD؛ ينخفض بحدة إن اعتُبر Working Tree هو مصدر التشغيل.

---

## 14. Open Questions / Decisions Needed

مستمدة من Workflow §9 ومن فجوات التدقيق:

1. هل إدارة الحجاج شاشة مستقلة أم ربط تشغيلي فقط؟  
2. هل يُسلَّم تصميم GPS/تكامل أم يُعرض موقع/حالة دون ادعاء Live؟  
3. هل الشكاوى داخل Phase 2 كاملة أم نسخة محدودة؟ (المستودع: خارج النطاق)  
4. قواعد اعتماد المشتريات وحدود المبالغ؟  
5. هل «11 وحدة تشغيلية» شاشات مستقلة أم تقسيم تنظيمي؟  
6. هل مدة 8 أسابيع تعاقدية أم تخطيطية؟  
7. **جديد:** هل يُعتمد Workflow المرفق على حساب `OUT_OF_SCOPE.md` أم العكس؟  
8. **جديد:** هل انحدار Accommodation في Working Tree مقصود أم عرضي؟  
9. **جديد:** متى يُقبل أو يُرفض/يُستبدل ADR-0019؟

---

## 15. Exact Next Sprint Recommendation

**التوصية الفورية (قبل أي Sprint منتج جديد):**

> **Sprint 0 — Phase 2 Stabilization & Scope Lock (3–5 أيام عمل)**

**لماذا:**

- Working Tree الحالي يكسر الإسكان رغم وجود كود ومراجعات قبول على HEAD.  
- بدون قفل النطاق (Workflow vs OUT_OF_SCOPE) سيُهدر الجهد على S04+ غير المعتمدة داخليًا.  
- Frontend المواسم/النقل هو المسار الأقصر لجعل Backend الحالي قابلاً للتشغيل الميداني.

**مخرجات Sprint 0:**

1. Working tree نظيف أو commit واعٍ يعيد ربط Accommodation.  
2. قرار مكتوب للنطاق المتبقي (S04–S12).  
3. Backlog مُجزّأ: Foundation UI → Transport UI → Accommodation close-out.  
4. قائمة تحقق S11 مصغّرة للثلاثي التشغيلي فقط.

**لا يُنصح** ببدء S04 المشتريات أو S09/S10 قبل إغلاق الثلاثي التشغيلي المعتمد في المستودع.

---

## ملحق أ — خريطة أدلة سريعة (مسارات)

| موضوع | مسار دال |
|---|---|
| Foundation BE | `backend/app/Modules/SeasonPilgrims/` |
| Transport BE | `backend/app/Modules/Transportation/` |
| Accommodation BE | `backend/app/Modules/Accommodation/` |
| Providers (WT) | `backend/bootstrap/providers.php` — SeasonPilgrims + Transportation فقط |
| API Transport | `backend/routes/api.php` (~سطر 303+) |
| Permissions Transport/Seasons | `backend/app/Core/Authorization/PermissionCatalog.php` |
| Accommodation FE | `frontend/src/modules/accommodation/` |
| Router الحالي | `frontend/src/app/router/index.ts` — بدون accommodation في WT |
| قبول إسكان | `docs/reviews/ACCOMMODATION_SPRINT3_ACCEPTANCE.md` |
| خارج النطاق | `docs/00-project/OUT_OF_SCOPE.md` |
| ROADMAP (قديم جزئيًا) | `docs/00-project/ROADMAP.md` يقول Accommodation Not started |

---

## ملحق ب — تمييز Mock / Live

| سطح | Live؟ | ملاحظة |
|---|---|---|
| Dashboard MVP | Live من APIs الموديولات المنفّذة | بلا نقل/إسكان/GPS |
| Transport GPS | لا | غير منفّذ؛ ممنوع كادعاء Live |
| Accommodation UI | Live API على HEAD | مكسور محليًا في WT |
| خرائط تنفيذية | لا | غير منفّذة |

---

*نهاية تقرير التدقيق. لم يُعدَّل أي كود تطبيقي؛ الملف الوحيد المُنشأ هو هذا التقرير.*
