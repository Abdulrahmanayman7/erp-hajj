# ERP Hajj — Playwright E2E

End-to-end coverage for **UI + API + workflows** against a real environment (default: Hostinger perf fixture).

## Setup

```bash
cd e2e
npm install
npm run install:browsers
copy .env.e2e.example .env.e2e   # Windows
# then edit .env.e2e with email/password
```

`.env.e2e` is gitignored. Never commit credentials.

## Run

```bash
cd e2e
npm test                 # full suite
npm run test:auth        # login only
npm run test:api         # backend API smoke
npm run test:nav         # frontend route smoke
npm run test:workflows   # cross-module workflows
npm run test:headed      # headed browser
npm run report           # open HTML report
```

## What is covered

| Suite | Scope |
|---|---|
| `auth.setup.ts` | Saves authenticated storage state |
| `auth.spec.ts` | Login success + invalid password |
| `api-smoke.spec.ts` | Sanctum session + authenticated list endpoints |
| `nav-smoke.spec.ts` | Opens all static `/app` list/create pages |
| `pages-details.spec.ts` | Opens detail pages when fixture data exists |
| `workflows/governance` | Meeting → Decision → Task chain |
| `workflows/contracts` | Contract draft + submit-review |
| `workflows/seasons-pilgrims` | Season + pilgrim + program + enroll |
| `workflows/procurement` | Supplier + purchase request draft/submit |
| `workflows/transport-gps` | Vehicles/points/drivers + GPS simulator provider |
| `workflows/inventory-assets` | Warehouse → item → asset |
| `workflows/risks-complaints` | Risk + complaint create (season-scoped) |
| `workflows/season-projects` | Season project create/activate |
| `workflows/hr-org` | Org unit + employee |
| `workflows/accommodation` | Facility create + accommodation pages |

## Notes

- Target is configured via `E2E_BASE_URL` / `E2E_API_URL`.
- Workers are serial (`workers: 1`) to avoid fixture collisions on shared live data.
- Creates use unique timestamps; responses `403`/`422` are tolerated where permissions/validation differ by seed.
- Prefer the perf/large fixture tenant for live runs — do not point this suite at sensitive production tenants without Owner approval.
