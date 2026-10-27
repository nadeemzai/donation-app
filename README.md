# Donation Management System

Donation platform for a single NGO in Pakistan: donors give through the website or mobile app (JazzCash, Easypaisa, bank/Raast), staff run everything from a Filament admin panel, and Zakat and other funds are kept separate in a fund ledger.

**Stack:** Laravel 13 · Filament 5 · Livewire 4 · Blade + Alpine.js · Sanctum REST API (`/api/v1`) · MySQL 8 · Pest 5

## Repository layout

| Path | What it is |
|---|---|
| `CLAUDE.md` | Build rules for Claude Code (read first) |
| `docs/srs.md` | Requirements — source of truth |
| `docs/Donation_Management_System_SRS_BRD.docx` | Same requirements, Word version |
| `docs/prompts/week-NN.md` | Weekly task prompts |
| `donation-app/` | The Laravel application |
| `setup.sh` | One-time setup (already run) |
| `.github/workflows/ci.yml` | GitHub Actions CI: Pint, Larastan, tests on MySQL 8 |

## Prerequisites

- PHP 8.4+ (Laravel Herd) with bcmath, intl, pcntl, gd, exif, pdo_mysql
- Composer 2, Node 20+, MySQL 8, git

## Setup on a new machine

```bash
git clone <repo-url> donation-system && cd donation-system/donation-app
composer install
cp .env.example .env && php artisan key:generate   # then set DB_* in .env
php artisan migrate
npm install && npm run build
herd link donation-app                              # http://donation-app.test
```

(First-ever setup used `bash setup.sh` from the repository root.)

## Daily commands (from `donation-app/`)

```bash
composer run dev     # app + queue + vite
composer check       # Pint + Larastan + tests — must pass before every merge
composer fix         # apply Pint formatting
php artisan migrate
```

## Git workflow

- `main` = production, `develop` = integration.
- One branch per task: `feature/w01-d1-verify-setup`.
- Commit messages: `W01-D1: <what> (FR-ACC-01)`.
