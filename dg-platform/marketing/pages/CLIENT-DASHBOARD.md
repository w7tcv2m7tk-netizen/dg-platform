# Client Dashboard — Oxygen Update

Paste into **Oxygen → Code Block** on `/client-dashboard/`.

**Legacy URL:** `/system-pages/client-dashboard/` redirects automatically (301) after plugin v10.34.0.

**Use:** `marketing/pages/client-dashboard-oxygen.html` (not the full `client-dashboard.html` document).

## Oxygen steps

1. Open **Pages → System Pages → Client Dashboard** in Oxygen
2. Select the **HTML Code** element (or add one — full width)
3. **Delete** all existing code in the element
4. Paste the entire contents of `client-dashboard-oxygen.html`
5. In element settings, enable **Execute PHP** (required for welcome name + setup progress)
6. Remove duplicate headers/sections if the page has old portal cards or cookie-login PHP
7. Save → purge Cloudflare → test logged in as a `dg_client` user

## Page settings (recommended)

- **Template:** blank / no header if possible (dashboard has its own header)
- **Only one** HTML Code element for this page — avoid nested old markup

## What changed

| Old | New |
|-----|-----|
| Cookie login check | WordPress login (`DG_Client_Portal` guards this page) |
| My account | `/client-account/` (was `/customer-account/`) |
| Static fake activity | Setup progress from CRM contact tags |
| `[Client Name]` | Live WP user display name |

## Tags used for setup progress

- `Payment Received` — Stripe webhook
- `Onboarding Complete` — onboarding form submitted
- `Platform Live` — you add manually when client goes live

## Troubleshooting

| Issue | Fix |
|-------|-----|
| PHP shows as raw text | Enable **Execute PHP** on HTML Code element |
| Broken layout / double header | Remove `<html>`, `<head>`, `<body>` — use oxygen file only |
| Always says "Welcome back, there" | User not logged in, or PHP not executing |
| Progress stuck on Pending | Contact not linked — check user meta `dg_contact_id` |
