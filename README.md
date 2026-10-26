# Donation Management System

Single-NGO donation platform: Filament 5 admin, Blade + Alpine + Livewire public site, REST API for the mobile app. Laravel 13.

## First-time setup (macOS)

Prerequisites: PHP 8.3+ (Laravel Herd), Composer, Node 20+, MySQL 8 running, git.

```bash
cd ~/Projects/donation-system
bash setup.sh            # edit DB_* at the top first if your MySQL isn't root / no password / 3306
```

The script creates `donation-app/`, installs all packages, configures `.env`, migrates, runs checks and makes the first commit.

## Daily

```bash
cd donation-app
composer run dev         # app + queue + vite
composer check           # pint + larastan + tests
```

- Build rules for Claude Code: [CLAUDE.md](CLAUDE.md)
- Requirements: [docs/srs.md](docs/srs.md) · Word version in `docs/`
- Weekly prompts: `docs/prompts/`
