# CLAUDE.md — Donation Management System

Read this file fully at the start of every session. Requirements live in `docs/srs.md`; this file is how we build them.

## Project

- Donation management system for **one NGO** (no multi-tenancy, no `tenant_id` anywhere).
- Market: Pakistan. Currency PKR. Timezone `Asia/Karachi`. Fiscal year July–June.
- Three entry points, one codebase, one set of domain services:
  1. **Admin panel**: Filament 5 at `/admin` (staff, guard `web`, model `User`).
  2. **Public website + donor portal**: Blade + Alpine.js + Livewire 4 (guard `donor`, model `Donor`).
  3. **Mobile REST API**: `/api/v1`, Laravel Sanctum tokens issued to `Donor`. The mobile app itself is a separate project.
- **Inertia.js is not used.** Do not install it or suggest it.

## Repository layout

```
donation-system/            ← git root; start Claude Code here
  CLAUDE.md                 ← this file
  setup.sh                  ← one-time setup (already run)
  bitbucket-pipelines.yml
  docs/
    srs.md                  ← requirements (source of truth)
    Donation_Management_System_SRS_BRD.docx
    prompts/week-NN.md      ← weekly task prompts
  donation-app/             ← the Laravel 13 application
```

**Run every `php artisan`, `composer`, `npm` and `./vendor/bin/*` command from `donation-app/`.** Paths below under `app/`, `tests/`, `database/` are relative to `donation-app/`.

## Stack (pin exact versions in composer.lock)

| Concern | Package |
|---|---|
| Framework | Laravel 13, PHP 8.4+ (Symfony 8 requires it) |
| Admin | filament/filament ^5 (built-in MFA for staff) |
| Roles/permissions | spatie/laravel-permission + bezhansalleh/filament-shield (check Filament 5 compatibility before installing; if unsupported, use spatie/laravel-permission + policies only) |
| Audit | spatie/laravel-activitylog |
| Media | spatie/laravel-medialibrary + filament/spatie-laravel-media-library-plugin |
| Settings | spatie/laravel-settings + filament/spatie-laravel-settings-plugin |
| PDF | spatie/laravel-pdf (Chromium; Urdu-safe) |
| API docs | knuckleswtf/scribe |
| Queue | Redis + laravel/horizon |
| Tests / quality | pestphp/pest, larastan/larastan (level 6+), laravel/pint |
| Backups | spatie/laravel-backup |
| WhatsApp | Green API over Laravel `Http` client (custom notification channel, no SDK) |

Do not add a package that is not in this table without asking first and explaining why.

## Folder conventions

```
app/
  Actions/            # one public handle() per class, e.g. ConfirmDonation, ApproveDisbursement
  Enums/              # PHP backed enums for every status/type column
  Events/ Listeners/ Jobs/
  Filament/           # Resources, Pages, Widgets, Clusters
  Http/Controllers/Api/V1/   # thin; FormRequest in, JsonResource out
  Http/Requests/Api/V1/
  Http/Resources/V1/
  Livewire/           # donate wizard, zakat calculator, donor portal
  Models/
  Payments/           # Contracts/PaymentGateway.php, Gateways/*, DTOs/*
  Policies/
  Services/           # DonationService, FundLedgerService, PaymentService, ReceiptService, DisbursementService, NumberSequenceService
  Settings/           # spatie settings classes
  Support/            # small helpers, e.g. ApiResponse
tests/Feature, tests/Unit
```

## Non-negotiable rules (money and data)

1. **Money:** `decimal(14,2)` columns, cast with `'decimal:2'`. Never use float. Do arithmetic with `bcadd`/`bcsub`/`bccomp` (scale 2) or integer paisa inside services.
2. **Status changes and money movement only through `app/Services` or `app/Actions`**, inside `DB::transaction()`, using `lockForUpdate()` on the fund row when a balance changes. Filament actions, Livewire components and API controllers call the service; they never update status or balance columns directly.
3. **Ledger is append-only.** `fund_ledger_entries` has no update or delete path in code. Corrections are reversal entries.
4. **Paid donations are immutable** (BR-05). No delete on donations, payments, receipts or ledger entries. Soft deletes only on master data (donors, beneficiaries, campaigns, projects).
5. **Zakat rule (BR-02):** a disbursement from a fund where `is_zakat = true` must target a beneficiary with `is_zakat_eligible = true`. Enforce in `DisbursementService` and cover with a test.
6. **Fund balance can never go negative (BR-07).** Check inside the locked transaction at approval and at payout.
7. **Public numbers** (`donation_no`, `receipt_no`, `disbursement_no`) come from `NumberSequenceService` (locked row in a `number_sequences` table, format `PREFIX-YYYY-000001`). Never derive them from auto-increment IDs.
8. **Webhooks:** verify signature/hash, compare amount and currency, enforce idempotency on `gateway_ref` (unique index), store the raw payload in `payment_webhook_logs` before processing.
9. **Sensitive data:** `cnic`, `ntn` and gateway secrets use `encrypted` casts. Store `cnic_hash` (HMAC with app key) for uniqueness and lookups.
10. **No card data** ever touches our server. Card payments are **Phase 2** — do not build a card adapter now; MVP gateways are JazzCash, Easypaisa and manual bank/Raast.
11. **Two-person approval for disbursements (BR-06)**, with one exception: if `ApprovalSettings::allow_founder_self_approval` is true, a `super_admin` may approve their own request. The approval record gets `is_self_approved = true` and an activity-log entry. When the setting is false, the requester can never approve their own request. Enforce in `DisbursementService` and the policy, and test both settings.
12. **WhatsApp via Green API:** only to donors with WhatsApp opt-in; always queued; rate-limited per minute; phone normalised `03XXXXXXXXX` → `923XXXXXXXXX@c.us`; every send logged in `message_logs`. Green API calls go through one `GreenApiClient` class behind a `WhatsAppChannel` interface so it can be swapped for WhatsApp Cloud API later. Email is always sent alongside for receipts.

## Coding conventions

- `declare(strict_types=1);` in every PHP file. Typed properties, parameters and returns.
- Every status/type column has a backed enum in `app/Enums` with `label()` and, for Filament, `HasLabel`/`HasColor`.
- Models: mass-assignable fields declared explicitly with Laravel 13's `#[Fillable([...])]` / `#[Hidden([...])]` attributes (never `$guarded = []` or `#[Unguarded]`), relationships typed, casts in `casts()` method.
- Every model gets a factory. Every resource gets a policy. Register nothing by hand that auto-discovery handles.
- Validation in FormRequests (API/web) or Filament form rules; business rules in services.
- Queue anything slow or external: PDFs, email, SMS, WhatsApp, reconciliation, gateway status checks.
- API responses use one envelope: `{ success, data, message, errors, meta }`. Validation errors return 422 with field errors.
- Config for gateways, prefixes and thresholds comes from `app/Settings`, never hard-coded and never `env()` outside config files.
- Translation-ready strings on the public site (`__()`). English at launch, Urdu/RTL later.
- Reference requirement IDs in tests and commit messages, e.g. `it('blocks zakat payout to non-eligible beneficiary') // BR-02`.

## Testing rules

- Pest only. Feature tests for every service method that moves money or changes status.
- Money paths (ledger, confirmation, refund, disbursement, webhooks) are written **test-first**.
- Fake external services: `Http::fake()`, `Queue::fake()`, `Notification::fake()`, `Storage::fake()`.
- Before saying a task is done, run `composer check` in `donation-app/` (Pint, Larastan, tests). All three must pass. Report the results.

## How to work in this repo

1. Read `docs/srs.md` (repo root) sections relevant to the task before writing code.
2. For any task larger than one file: propose a plan first (files to create/change, migrations, tests). Wait for approval.
3. Build in this order: migration → enum → model → factory → policy → service/action → Filament resource or controller → tests.
4. Keep changes scoped to the task. Do not refactor unrelated code.
5. If a requirement is unclear or conflicts with `docs/srs.md`, stop and ask. Do not guess on money or permissions.
6. After finishing, give a short summary: files changed, migrations added, tests added, commands run and their result.

## Git

- Branch per task: `feature/w01-d1-project-setup`. Merge to `develop`; `main` is production.
- Commit message: `W01-D1: <what> (FR-ACC-01)`.
- Never commit `.env`, credentials or gateway keys.

## Commands (from donation-app/)

```
composer run dev          # serve + queue + vite
composer check            # pint --test + larastan + tests (must pass before done)
composer fix              # apply pint
php artisan horizon       # queues (once Redis is configured)
php artisan scribe:generate
```

Local queue/cache/session use the `database` driver; Redis + Horizon are for staging/production.

## Roles (seeded)

`super_admin`, `manager`, `finance`, `campaign_manager`, `field_officer`, `trustee`, `auditor`. Permission matrix: `docs/srs.md` §3.1.
