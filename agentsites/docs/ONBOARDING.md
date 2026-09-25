# ONBOARDING.md — from the landing page to a live site in under two minutes

Spec §13, acceptance test A2. Everything happens on `app.{base}`; the site itself is served from
`{slug}.{base}` the moment it is published. `{base}` is `PLATFORM_BASE_DOMAIN` from `.env`.

## The flow

| Screen | URL | What happens |
| --- | --- | --- |
| S0 Landing | `{base}/` (or `app.{base}/` while the legacy platform keeps the apex) | headline, CTA → `/start`, three theme previews, live counter of published sites |
| S1 Sign in | `app.{base}/start` | Continue with Google · email → 6-digit code **and** magic link in one email · optional phone → WhatsApp code. First sign-in creates the account (trial, 14 days), the owner user and a **draft site** so autosave has a target |
| S2 About you (1/3) | `/onboarding?step=1` | name (prefilled), WhatsApp (validated live, E.164), agency (autocomplete from `config/onboarding.php`, free text allowed), licence/BRN (optional), photo (camera on phones, square WebP) |
| S3 Your website (2/3) | `/onboarding?step=2` | subdomain prefilled from the name, availability check after 300 ms with three suggestions, theme cards rendered with the agent's own name/photo, six palettes + "From my logo" |
| S4 Go live (3/3) | `/onboarding?step=3` | area chips, a full-width iframe of the **real** draft site, "Publish my website" / "Finish later" |
| S5 Success | `/onboarding/success` | confetti, copyable URL, QR code, Share on WhatsApp, Open my site, checklist with % complete |

Typed fields: email, code, name, WhatsApp, agency (+ licence and subdomain when the agent wants
to change them) — six at most (§13). Every change is saved immediately into `tenants.config`;
leaving and coming back resumes at the last step reached (`tenants.onboarding_step`).

## Sign-in rules (§17)

- Codes: 6 digits, 10 minutes, 5 attempts, then the code is burnt; one live code per identifier.
- Limits: 3 codes per identifier and 10 per IP every 10 minutes; 30 verifications per IP.
- Codes are stored as keyed hashes (`otp_codes.code_hash`); the plain code lives only in the message.
- The magic link is a signed URL, valid 10 minutes, single use.
- Google: `GOOGLE_CLIENT_ID/SECRET`; the redirect URI is `https://app.{base}/auth/google/callback`.
- Turnstile appears on the sign-in forms as soon as `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY` are set.
- Sessions are cookies on the app host only (`SESSION_DOMAIN` unset): a tenant site never sees them.

## What publish does

`PublishTenant` refuses (`CannotPublish`) until the draft has a name and a valid WhatsApp number.
Then: status `live`, `published_at`, `seo.noindex = false`, host cache purged, `published` +
`onboarding.completed` events, the welcome WhatsApp (via the configured `Notifier`), and — when
`CONTENT_GENERATOR=claude` — the `GenerateContent` job that replaces the template copy.

## Events (funnel)

`onboarding.started` (S1 viewed, once per session), `account.created`, `step.viewed {n}`,
`step.completed {n}`, `theme.selected {theme}`, `slug.checked {slug, available}`, `published
{first}`, `share.clicked {channel}`, `reminder.sent {kind, channels}`.

```sh
php artisan platform:funnel --days=7          # starts, sign-ups, per-step views/completions, drop-off, median seconds, published
php artisan platform:funnel --days=30 --json  # the same report for the admin dashboard (Phase 6)
```

## Abandonment reminders

Every 15 minutes the scheduler runs `SendOnboardingReminders`: drafts untouched for 1 h get one
reminder, drafts untouched for 24 h another — email always, WhatsApp when the phone is verified —
with a resume link and a signed opt-out link (`users.reminders_opted_out_at`). Never twice.

## Local and staging without real providers

```sh
MAIL_MAILER=file          # storage/app/private/mail-sink/*.json  (subject carries the code)
WHATSAPP_PROVIDER=file    # storage/app/private/whatsapp-sink/*.json
CONTENT_GENERATOR=template
```

## Verifying it end to end

```sh
cd app && npm run e2e         # Playwright at 375×667: generated sites + the whole onboarding flow (A2)
npm run e2e:timing            # 10 timed runs landing → published, median in tests/E2E/timing.json
```
