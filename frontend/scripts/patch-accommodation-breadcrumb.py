from pathlib import Path

path = Path(r"d:\erp-hajj-1\frontend\src\app\layouts\AppTopbar.vue")
text = path.read_text(encoding="utf-8")

old = """  } else if (path.startsWith('/app/my-custodies')) {
    crumbs.push({ label: t('nav.myCustodies'), to: null })
  } else if (path.startsWith('/app/audit')) {
"""

new = """  } else if (path.startsWith('/app/my-custodies')) {
    crumbs.push({ label: t('nav.myCustodies'), to: null })
  } else if (path.startsWith('/app/accommodation')) {
    crumbs.push({ label: t('nav.accommodation'), to: '/app/accommodation' })
    if (path.includes('/allocation')) {
      crumbs.push({ label: t('accommodation.nav.allocation'), to: null })
    } else if (path.includes('/drafts')) {
      crumbs.push({ label: t('accommodation.nav.drafts'), to: null })
    } else if (path.includes('/scopes')) {
      crumbs.push({ label: t('accommodation.nav.scopes'), to: null })
    } else if (path.includes('/facilities/')) {
      crumbs.push({ label: t('accommodation.facilityDetails.title'), to: null })
    } else {
      crumbs[crumbs.length - 1] = { label: t('nav.accommodation'), to: null }
    }
  } else if (path.startsWith('/app/audit')) {
"""

if "path.startsWith('/app/accommodation')" in text:
    print("already-patched")
elif old not in text:
    print("pattern-not-found")
    raise SystemExit(1)
else:
    path.write_text(text.replace(old, new, 1), encoding="utf-8")
    print("patched")
