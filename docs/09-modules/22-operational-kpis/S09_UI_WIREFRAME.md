# S09 — UI Wireframe (Textual, Arabic RTL)

> **Status:** PROPOSED / NOT_IMPLEMENTED  
> **Date:** 2026-11-08  
> **No charts library. No S10 map.**

---

## Placement options (Owner S09-D07)

1. Section under existing `/app` Dashboard below MVP KPI row.  
2. Dedicated `/app/operational-kpis` with nav entry (permission-gated).

## Layout (RTL)

```
┌────────────────────────────────────────────────────────────┐
│  مؤشرات التشغيل الموسمية                    [موسم ▼] [تحديث] │
├────────────────────────────────────────────────────────────┤
│  تنبيه حالة: لا موسم / صلاحية / مصدر غير متاح (إن وُجد)      │
├──────────────┬──────────────┬──────────────┬───────────────┤
│ بطاقة KPI    │ بطاقة KPI    │ بطاقة KPI    │ …             │
│ عنوان عربي   │ قيمة | وحدة  │ حالة شدة     │ رابط تفصيلي   │
└──────────────┴──────────────┴──────────────┴───────────────┘
│ قسم: النقل                                                  │
│ قسم: الإسكان                                                │
│ قسم: المشتريات                                              │
│ قسم: المشاريع الموسمية                                      │
│ قسم: المخاطر والشكاوى                                       │
└────────────────────────────────────────────────────────────┘
```

## Components (reuse / extend)

| Piece | Notes |
|---|---|
| Season selector | Tenant seasons only; invalid selection → error state |
| KPI card | Reuse Dashboard KPI card visual language |
| Domain section | Collapsible; hidden entirely if no permitted cards |
| States | Loading skeleton; Empty; Error; N/A badge; Unavailable badge |
| Numbers | Arabic-Indic or locale formatting consistent with app |
| Accessibility | Text labels; not color-only severity |
| Responsive | 1→2→3 columns; no horizontal trap |
| Drill-down | Optional safe `/app/...` links (allow-listed) |

## Explicit exclusions

- Live map / GPS widgets (S10).  
- Heavy charts (unless later Owner CR).  
- Showing unauthorized domains as 0.
