# Authentication — UI (planned)

> **Status:** Planned — no pages exist
> **Last updated:** 2026-08-06

## Pages

- **Login page** — Arabic RTL, standalone layout (no app shell), form validation, generic failure message (no account-existence disclosure).
- **Logout action** — from the app header user menu.

## Behavior

- Route guards redirect unauthenticated users to login and authenticated users away from it.
- After login, the frontend loads the current user + permissions (`auth/me`) before rendering the shell.

## TBD

- Password reset UI: TBD (flow not yet approved).
- Branding on the login page (logo/colors within the approved palette): TBD.
