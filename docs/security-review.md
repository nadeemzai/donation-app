# Security review — Week 12 Day 1

Reviewed against the OWASP Top 10 (2021) and the SRS (NFR-04, FR-ACC-01/02). Status: ✅ in place · 🔧 fixed today · ⏳ go-live task.

Automated guards (they fail CI if a later change breaks them):
- `tests/Feature/Security/PermissionsAuditTest.php` — every admin resource has a policy; every admin screen is shut to a role-less user and to guests; a deactivated user is locked out; every non-public API route needs a Sanctum token or a signed URL; every public write endpoint is rate-limited.
- `tests/Feature/Access/*` — the full role × screen matrix (config/permissions.php).
- `php artisan security:check` — production settings (run on the server, see below).

## OWASP Top 10

| # | Risk | Status | How |
| --- | --- | --- | --- |
| A01 | Broken access control | ✅ 🔧 | Policies on every model; matrix in `config/permissions.php`; Super Admin bypass only for matrix abilities, never policy rules (e.g. paid donations can't be deleted). Field officers see only their households (`docs/beneficiary-data-access.md`). Private files on the `local` disk, signed URLs, downloads audited. 🔧 Route-wide audit test added. 🔧 The last active Super Admin can no longer be deactivated or demoted. |
| A02 | Cryptographic failures | ✅ ⏳ | CNIC and payout accounts encrypted (+ HMAC hash for search), 2FA secrets and recovery codes encrypted, passwords bcrypt. HSTS in production. ⏳ HTTPS-only session cookie (`SESSION_SECURE_COOKIE=true`) and an off-server backup of `APP_KEY`. |
| A03 | Injection | ✅ | Eloquent / bound parameters everywhere; the few raw SQL fragments contain no user input. Blade escapes output; rich text from staff goes through `SafeHtml::clean`; JSON-LD uses `JSON_HEX_TAG`. Settlement CSVs are parsed as data only. |
| A04 | Insecure design | ✅ | Money rules in services with row locks (BR-03/06/07), immutable ledger, nightly `ledger:verify`, two-person approval for payouts, Zakat ring-fencing, idempotent webhooks. |
| A05 | Security misconfiguration | 🔧 ⏳ | 🔧 Only our own domain is answered (`trustHosts`), so a forged Host header can't reach generated links. 🔧 Headers: X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, a narrow Content-Security-Policy (`object-src 'none'; base-uri 'self'; frame-ancestors 'self'`), `X-Powered-By` removed. ⏳ `security:check` must pass on the server (APP_DEBUG=false, live gateways, real queue/mail). |
| A06 | Vulnerable components | 🔧 ⏳ | 🔧 `composer audit` on 11 Nov 2026 found 12 advisories (Symfony components ≤ 8.1.9, PHPUnit 13.4.3); fixed by updating to Symfony 8.1.11+ and PHPUnit 13.4.4+. ⏳ Run `composer audit` and `npm audit --omit=dev` before go-live and monthly; keep Laravel/Filament on supported versions. |
| A07 | Identification & authentication | ✅ 🔧 | Authenticator app required for Super Admin, Finance, Trustee; Filament login and 2FA challenge are rate-limited; donors use one-time codes (rate-limited) and Sanctum tokens. 🔧 Password rule: 10+ characters; in production also letters + numbers and not in known breaches. 🔧 Super Admin "Reset two-factor" (signs the person out everywhere) and `php artisan mfa:reset` for a locked-out founder. 🔧 Clearer set-up instructions. |
| A08 | Software & data integrity | ✅ | Gateway callbacks verified by hash/signature before anything changes; replayed callbacks do nothing; settlement files hashed and never imported twice. |
| A09 | Logging & monitoring | ✅ ⏳ | Audit log for money, approvals, exports, downloads, data access, 2FA resets; login history. ⏳ Error monitoring and alerts on `ledger:verify` failure (Week 12 Day 4). |
| A10 | SSRF | ✅ | The server only calls fixed gateway / WhatsApp / mail hosts from settings; no user-supplied URLs are fetched. |

## Rate limits
Web: donate / Qurbani 10/min, donor login codes 10/min, contact 5/min, payment returns 60/min, JazzCash server callbacks 600/min (hash-checked), pledges 10–20/min. API: 60/min per token/IP, login / OTP 5/min and 20/hour per IP, donate 10/min. Admin: Filament's login and 2FA throttles. WhatsApp sending: per-minute limit from Settings.

## Decisions kept as they are
- Only the Super Admin decides applications and Zakat eligibility (`beneficiaries.approve`). Managers can be given this later by changing one line in `config/permissions.php` and re-running the roles seeder.
- No strict script Content-Security-Policy: Livewire / Alpine need inline scripts; the narrow CSP above blocks the dangerous parts without breaking the panel or the gateway redirect forms.

## Two-factor: when someone loses their phone
1. Call them back on a number you already have — never reset on an e-mail or WhatsApp request alone.
2. Admin → Settings → Staff users → **Reset two-factor** on their row (write the reason).
3. They log in with their password and set up a new authenticator; their old sessions are already closed.
4. If **every** Super Admin is locked out: on the server, `php artisan mfa:reset founder@example.org --reason="Lost phone"`.

## Go-live security checklist (Week 12 Day 4–5)
- [ ] `php artisan security:check` passes on the production server (no FAIL).
- [ ] `APP_KEY` copied into the NGO password manager **and** a sealed offline copy. Without it, encrypted CNICs, accounts and 2FA secrets are lost for ever. Never change it after go-live.
- [ ] At least **two** active Super Admins, both with their authenticator set up and recovery codes stored safely.
- [ ] Every Finance and Trustee account has its authenticator set up.
- [ ] `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`, `LOG_LEVEL=warning`, HTTPS certificate auto-renews.
- [ ] Gateways in **live** mode with live keys entered in Settings (never in chat or e-mail).
- [ ] `composer audit` clean; demo data not seeded in production.
- [ ] Backups run and a restore has been tested (Day 4).
