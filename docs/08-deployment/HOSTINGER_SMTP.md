# Production mail (Hostinger) — password invites

> **Status:** Operational guidance  
> **Last updated:** 2026-09-15

## Purpose

Password-set invitations (`send_invite` on user create) reuse the Auth password-reset mailer. If `MAIL_MAILER` is `log` or `array`, the API returns `invite_sent: false` with `invite_code: INVITE_MAILER_UNAVAILABLE` and the UI forces the manual temporary-password path.

## Hostinger SMTP (example)

Set on the server `.env` only (never commit secrets):

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=noreply@YOUR-DOMAIN.com
MAIL_PASSWORD=YOUR_MAIL_PASSWORD
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="noreply@YOUR-DOMAIN.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Then:

```bash
php artisan config:clear
php artisan config:cache
```

Confirm `FRONTEND_URL` matches the SPA origin so reset links open correctly.

Optional per-tenant From identity (not SMTP credentials) is configured in **Settings → إعدادات البريد الإلكتروني** (`mail_from_address` / `mail_from_name`). Empty values fall back to `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` above.

## Related

- [docs/09-modules/15-system-settings/BUSINESS_RULES.md](../09-modules/15-system-settings/BUSINESS_RULES.md)
- [docs/09-modules/02-users-and-authorization/BUSINESS_RULES.md](../09-modules/02-users-and-authorization/BUSINESS_RULES.md) — invite vs temporary password
- [DEPLOYMENT.md](DEPLOYMENT.md)
