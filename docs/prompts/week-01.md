# Week 1 — Foundation & access control (2–6 Nov 2026)

**Goal:** a running skeleton with staff auth + MFA, roles, settings, CI and audit logging.
**Definition of done:** admin can log in with MFA, manage users/roles/settings; CI passes on every push.

## How to use these prompts

- One prompt = one Claude Code session = one branch. Start each session in **plan mode** (Shift+Tab), paste the prompt, review the plan, then let it build.
- Before Prompt 1, run `bash setup.sh` once from the `donation-system` folder (creates `donation-app/` with every package).
- Start Claude Code from the `donation-system` folder (git root). It runs app commands inside `donation-app/`.
- After each prompt: review the diff yourself, run the checks, commit, merge to `develop`.
- If a session drifts, stop it and restart with the same prompt rather than patching in conversation.

---

## Prompt 1 — Day 1 · Verify setup, base migration, README

`setup.sh` already created Laravel 13, installed every package, published configs, set up Pest,
Larastan, Pint, composer scripts and the first commit. This prompt checks it and finishes Day 1.

```
Read CLAUDE.md and docs/srs.md sections 5.1, 5.2 and 6.2. The Laravel app is in donation-app/.

Task W01-D1a: verify the setup and finish the project base.

1. Review setup.log and donation-app/composer.json. List installed versions of laravel/framework,
   filament/filament, livewire/livewire, pestphp/pest and every package in the CLAUDE.md stack table.
   Flag anything missing or on an unexpected major version. Report whether filament-shield
   was installed.
2. Run `composer check` in donation-app/ and fix any Larastan or Pint failures in the skeleton
   (do not lower the Larastan level).
3. Create the `number_sequences` migration (key, prefix, year, last_number unsigned bigint default 0,
   timestamps, unique key+year). The service comes on Day 5.
4. Review .env.example: grouped, commented, no real secrets, every key the app will need
   (app, db, redis, mail, filesystem/S3, Green API, optional sms, jazzcash/easypaisa, admin seed).
5. Write donation-system/README.md: 3-line summary, prerequisites (PHP 8.3+, MySQL 8, Node 20+, Herd),
   setup (`bash setup.sh`), daily commands, link to CLAUDE.md and docs/.

Acceptance:
- `php artisan migrate:fresh` runs cleanly on MySQL.
- `composer check` passes.

Show me the plan first.
```

## Prompt 2 — Day 1 · Admin panel base, arch tests, CI

```
Read CLAUDE.md (Stack, Testing rules, Git sections). Filament 5 is installed with an `admin` panel.

Task W01-D1b: configure the panel and lock in code rules.

1. AdminPanelProvider: brand name "Donation Management", primary colour navy/blue
   (Color::hex('#1F3A68') or closest palette), dark mode on, SPA mode on, database notifications on.
   Navigation groups in this order: Dashboard, Donations, Campaigns, Donors, Programs,
   Seasonal, Inventory, Finance, Content, Settings. Timezone display Asia/Karachi.
2. Add a Pest arch test (tests/ArchTest.php):
   - strict_types in app/
   - no dd/dump/ray/var_dump in app/
   - app/Enums contains only enums
   - controllers in app/Http/Controllers/Api/V1 do not use the DB facade
   - app/Models classes extend Illuminate\Database\Eloquent\Model or Authenticatable
3. Review bitbucket-pipelines.yml at the repo root. It runs from donation-app/. Confirm the
   PHP extensions match what composer.json needs; fix if not.

Acceptance:
- /admin shows the Filament login page with the brand name.
- `composer check` passes locally.
- I will push and confirm the pipeline run.

Show me the plan first.
```

## Prompt 3 — Day 2 · Guards: staff and donors

```
Read CLAUDE.md (Project section) and docs/srs.md FR-ACC-01, FR-DNR-01, FR-DNR-02, FR-API-02.

Task W01-D2: set up two separate authenticated user types.

1. Staff: keep the default `users` table/model for guard `web`, used only by Filament.
   Add columns: phone (nullable), is_active (bool, default true), last_login_at, last_login_ip.
   Inactive users cannot access the panel (implement FilamentUser::canAccessPanel).
2. Donors: create `donors` table + Donor model (Authenticatable, HasApiTokens, SoftDeletes) with
   fields from the ERD in docs/srs.md §6.3: donor_code (unique, from NumberSequenceService later —
   leave nullable for now), name, email (nullable, unique), phone (nullable, unique),
   cnic (encrypted cast, nullable), cnic_hash (nullable, unique), ntn (encrypted, nullable),
   type (enum DonorType: individual|corporate), city, password (nullable for guests),
   email_verified_at, phone_verified_at, preferences (json), last_login_at.
   At least one of email or phone is required (validate in a FormRequest later; add a DB check
   if MySQL supports it cleanly, otherwise note it).
3. Add guard `donor` (session, provider donors) and password broker `donors` in config/auth.php.
4. Sanctum is already installed (routes/api.php exists); tokens belong to Donor. Do not add API routes yet beyond
   GET /api/v1/health returning the standard envelope { success, data, message, errors, meta }.
5. Create app/Support/ApiResponse.php helper (or a trait) that builds that envelope,
   and make API exceptions/validation errors render in the same envelope for /api/* routes.
6. DonorFactory with states: guest(), registered(), corporate().
7. Tests: donor guard login works; staff guard cannot authenticate a donor and vice versa;
   /api/v1/health returns the envelope; 422 errors on api routes use the envelope;
   cnic is stored encrypted (raw DB value != plain value).

Acceptance: composer check passes.

Show me the plan first.
```

## Prompt 4 — Day 3 · Roles and permissions

```
Read CLAUDE.md (Roles section) and docs/srs.md §3.1 (permission matrix).

Task W01-D3a: role-based access for staff.

1. spatie/laravel-permission is installed and its migration published. Add HasRoles to User (guard `web` only — donors never get roles).
2. Check setup.log: if Filament Shield was installed, configure it (shield:setup / generate for the panel).
   If it was skipped, build a simple Roles resource in Filament instead
   (name + permission checkbox list grouped by resource) — do not install an incompatible version.
3. Seed 7 roles: super_admin, manager, finance, campaign_manager, field_officer, trustee, auditor.
   super_admin bypasses all checks via Gate::before.
4. Create a seeder that maps the §3.1 matrix to permissions. Since most resources don't exist yet,
   define permission names now as a PHP config/array (e.g. config/permissions.php) keyed by area,
   with abilities view_any, view, create, update, delete, approve, export — and assign per the matrix
   (F = all, C = view/create/update, A = view + approve, V = view_any/view, - = none).
   Future resources will read from this map.
5. Filament UserResource (Settings group): name, email, phone, role (single select), is_active,
   last login info read-only. Only super_admin can manage users. A user cannot deactivate themselves.
6. DatabaseSeeder creates one super_admin from env values (ADMIN_EMAIL, ADMIN_PASSWORD) — never
   hard-coded credentials.
7. Tests: each role can/cannot reach UserResource as per matrix; auditor is read-only;
   super_admin bypass works; inactive user is blocked from the panel.

Acceptance: composer check passes. Log in as each seeded role and see the right menu.

Show me the plan first.
```

## Prompt 5 — Day 3 · MFA, throttling, login history

```
Read CLAUDE.md and docs/srs.md FR-ACC-01, FR-ACC-04, NFR-04.

Task W01-D3b: secure the admin login.

1. Enable Filament's built-in multi-factor authentication (authenticator app). Make it REQUIRED for
   roles super_admin, finance and trustee, optional for everyone else. Users with a required role and
   no MFA set up are forced to set it up on next login.
   Also enable recovery codes.
2. Rate limit admin login: 5 attempts per minute per email+IP. Show Filament's normal error.
3. Create `login_histories` table (user_id nullable, email, ip, user_agent, successful bool,
   failure_reason nullable, created_at). Record every staff login attempt via Laravel auth events
   (Login, Failed, Lockout). Update users.last_login_at/ip on success.
4. Filament: read-only LoginHistoryResource under Settings (super_admin + auditor),
   filters by user, success, date range. Also a "Login history" relation manager on UserResource.
5. Add sensible security headers middleware for web routes (X-Frame-Options, X-Content-Type-Options,
   Referrer-Policy, Permissions-Policy). HSTS only when app.env = production.
6. Tests: lockout after 5 failures; successful and failed logins are recorded; finance user without
   MFA is redirected to MFA setup; headers present.

Acceptance: composer check passes.

Show me the plan first.
```

## Prompt 6 — Day 4 · Settings module

```
Read CLAUDE.md and docs/srs.md FR-ACC-03, FR-PAY-04, BR-04, BR-06.

Task W01-D4: configurable settings, editable from the admin panel.

1. spatie/laravel-settings and the Filament settings plugin are installed; settings migrations table is published.
2. Create settings classes in app/Settings:
   - GeneralSettings: org_name, org_legal_name, registration_no, tax_exemption_ref, address, phone,
     email, website, logo (path), receipt_footer_text, default_locale.
   - NumberingSettings: donation_prefix (DN), receipt_prefix (RC), disbursement_prefix (DS),
     donor_prefix (DR), pad_length (6).
   - ApprovalSettings: allow_founder_self_approval (bool, default true — see BR-06),
     second_level_threshold (decimal, PKR), trustee_threshold (decimal, PKR).
     Only super_admin can change allow_founder_self_approval, and every change is activity-logged.
   - GatewaySettings: for jazzcash, easypaisa and bank_transfer:
     enabled (bool), mode (sandbox|live), display_order (int), and credential fields.
     JazzCash: merchant_id, password, integrity_salt. Easypaisa: store_id, hash_key.
     Credential fields are stored ENCRYPTED (use the package's encrypted properties).
     bank_transfer holds bank name, account title, IBAN, Raast ID instead of credentials.
     No card gateway — card payments are Phase 2.
   - NotificationSettings: email_enabled, whatsapp_enabled, sms_enabled (default false),
     green_api_url, green_api_id_instance, green_api_token_instance (encrypted),
     whatsapp_rate_per_minute (default 20), sms provider keys (encrypted, optional).
     Defaults read from the GREEN_API_* values in .env via config.
3. Filament settings pages for each class under the Settings group. Only super_admin can edit
   GatewaySettings and NotificationSettings; manager can view/edit General and Approval.
   Secret fields show as password inputs and are never echoed back in plain text.
   NotificationSettings page gets a "Check WhatsApp connection" header action that calls Green API
   getStateInstance and shows the state (authorized / notAuthorized) — use Http::fake() in tests.
4. Settings migrations with sensible defaults.
5. Tests: defaults load; secrets are encrypted at rest; permissions on each page.

Acceptance: composer check passes.

Show me the plan first.
```

## Prompt 7 — Day 5 · Audit trail and number sequences

```
Read CLAUDE.md (rules 2, 3, 7) and docs/srs.md BR-04, FR-AUD-01, NFR-06, NFR-07.

Task W01-D5: audit logging and the numbering service. Write the NumberSequenceService tests FIRST.

1. NumberSequenceService (app/Services):
   - next(string $key): string — e.g. next('donation') returns "DN-2026-000001".
   - Prefix and pad length from NumberingSettings; year from now() in Asia/Karachi.
   - Uses DB::transaction + lockForUpdate on the number_sequences row; creates the row for a new
     year automatically; numbers restart at 1 each calendar year.
   - Never reuses or skips numbers under concurrency within a committed transaction.
2. Tests first:
   - sequential numbers; correct format and padding; new year resets to 000001;
   - different keys are independent;
   - 50 iterations in a loop produce 50 unique numbers;
   - if the outer transaction rolls back, document the behaviour (a gap is acceptable; a duplicate is not).
3. spatie/laravel-activitylog is installed. Create a reusable trait app/Models/Concerns/Auditable that
   logs fillable attributes, only dirty values, with before/after, and the causer. Apply to User and
   Donor now. Never log password, remember_token, two-factor secrets, or raw cnic/ntn.
4. Filament ActivityLogResource (Settings group, read-only): who, what model, event, when, and a
   before/after diff view. Visible to super_admin, manager, trustee, auditor.
5. Set Donor::creating to assign donor_code via NumberSequenceService('donor').

Acceptance:
- composer check passes.
- Week 1 definition of done met: MFA login, users/roles/settings manageable, audit visible, CI green.

Show me the plan first.
```

---

## End of week checklist

- [ ] All 7 branches merged to `develop`, pipeline green
- [ ] Logged in as each of the 7 roles and checked menus
- [ ] MFA enforced for super_admin / finance / trustee
- [ ] Gateway secrets confirmed encrypted in the `settings` table
- [ ] Tag `v0.1.0-w01` on `develop`
- [ ] Note anything that slipped into `docs/prompts/week-02.md` before writing it
