#!/usr/bin/env bash
# =============================================================================
#  Donation Management System — one-time project setup (macOS)
#
#  Creates the Laravel 13 app in ./donation-app and installs every package
#  listed in CLAUDE.md, then configures, migrates, lints and commits.
#
#  Usage (from the donation-system folder):
#      bash setup.sh
#
#  Safe to re-run: finished steps are skipped.
# =============================================================================
set -euo pipefail

# ----------------------------------------------------------------------------
# 1. Edit these if your local MySQL differs
#    Herd Pro / Homebrew MySQL: 127.0.0.1 · 3306 · root · (empty)
#    MAMP:                      127.0.0.1 · 8889 · root · root
# ----------------------------------------------------------------------------
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-donation_system}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"

APP_DIR="donation-app"
APP_NAME="Donation Management"
APP_URL="${APP_URL:-http://donation-app.test}"

# ----------------------------------------------------------------------------
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"
LOG="$ROOT/setup.log"
: > "$LOG"

blue()  { printf "\033[1;34m%s\033[0m\n" "$*"; }
green() { printf "\033[1;32m✔ %s\033[0m\n" "$*"; }
warn()  { printf "\033[1;33m⚠ %s\033[0m\n" "$*"; }
fail()  { printf "\033[1;31m✘ %s\033[0m\n" "$*"; echo "See $LOG for details."; exit 1; }
step()  { echo; blue "▶ $*"; echo "=== $* ===" >> "$LOG"; }
run()   { "$@" < /dev/null >> "$LOG" 2>&1; }

# Set or replace KEY=value in an env file (handles commented-out keys)
set_env() {
  local file="$1" key="$2" value="$3"
  if grep -qE "^#? ?${key}=" "$file"; then
    KEY="$key" VAL="$value" perl -pi -e 's/^#? ?\Q$ENV{KEY}\E=.*/$ENV{KEY}=$ENV{VAL}/' "$file"
  else
    printf "%s=%s\n" "$key" "$value" >> "$file"
  fi
}

# ----------------------------------------------------------------------------
step "Checking tools"
command -v php      >/dev/null || fail "PHP not found. Install Laravel Herd (herd.laravel.com) or add PHP 8.3+ to PATH."
command -v composer >/dev/null || fail "Composer not found. Herd includes it; otherwise brew install composer."
command -v node     >/dev/null || fail "Node.js not found. Install Node 20+ (Herd or brew install node)."
command -v git      >/dev/null || fail "git not found."

php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
  || fail "PHP $(php -r 'echo PHP_VERSION;') found; Laravel 13 needs 8.3+. In Herd: herd use 8.3 (or newer)."

# Capture the module list once and match in bash (piping php -m into grep -q breaks under pipefail)
PHP_MODULES="$(php -m | tr '[:upper:]' '[:lower:]')"
missing=""
for ext in bcmath ctype curl dom fileinfo intl mbstring openssl pcntl pdo_mysql posix tokenizer xml zip gd exif; do
  case $'\n'"$PHP_MODULES"$'\n' in
    *$'\n'"$ext"$'\n'*) ;;
    *) missing="$missing $ext" ;;
  esac
done
if [ -n "$missing" ]; then
  echo "PHP binary: $(command -v php)" >> "$LOG"
  echo "$PHP_MODULES" >> "$LOG"
  fail "Missing PHP extensions:$missing (PHP used: $(command -v php))"
fi
green "PHP $(php -r 'echo PHP_VERSION;'), Composer, Node $(node -v), git"

# Find a mysql client (Homebrew, Herd, MAMP)
MYSQL_BIN="$(command -v mysql || true)"
for c in /opt/homebrew/bin/mysql /usr/local/bin/mysql /Applications/MAMP/Library/bin/mysql80/bin/mysql /Applications/MAMP/Library/bin/mysql; do
  [ -z "$MYSQL_BIN" ] && [ -x "$c" ] && MYSQL_BIN="$c"
done

# ----------------------------------------------------------------------------
step "Creating database '$DB_DATABASE'"
if [ -n "$MYSQL_BIN" ]; then
  if MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
       -e "CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >> "$LOG" 2>&1; then
    green "Database ready"
  else
    fail "Could not connect to MySQL at $DB_HOST:$DB_PORT as $DB_USERNAME. Start MySQL or edit the DB_* values at the top of setup.sh."
  fi
else
  warn "mysql client not found — create database '$DB_DATABASE' yourself (TablePlus/DBngin), then re-run."
  exit 1
fi

# ----------------------------------------------------------------------------
step "Creating Laravel 13 app in ./$APP_DIR"
if [ -f "$APP_DIR/artisan" ]; then
  green "Already exists — skipping"
else
  run composer create-project laravel/laravel "$APP_DIR" "^13.0" --no-interaction --prefer-dist \
    || fail "composer create-project failed"
  green "Laravel $(cd "$APP_DIR" && php artisan --version)"
fi
cd "$APP_DIR"

# ----------------------------------------------------------------------------
step "Configuring .env"
[ -f .env ] || cp .env.example .env
for f in .env .env.example; do
  set_env "$f" APP_NAME "\"$APP_NAME\""
  set_env "$f" APP_URL "$APP_URL"
  set_env "$f" APP_TIMEZONE "Asia/Karachi"
  set_env "$f" APP_LOCALE "en"
  set_env "$f" DB_CONNECTION "mysql"
  set_env "$f" DB_HOST "$DB_HOST"
  set_env "$f" DB_PORT "$DB_PORT"
  set_env "$f" DB_DATABASE "$DB_DATABASE"
  set_env "$f" QUEUE_CONNECTION "database"
  set_env "$f" CACHE_STORE "database"
  set_env "$f" SESSION_DRIVER "database"
  set_env "$f" FILESYSTEM_DISK "local"
done
set_env .env DB_USERNAME "$DB_USERNAME"
set_env .env DB_PASSWORD "$DB_PASSWORD"
set_env .env.example DB_USERNAME "root"
set_env .env.example DB_PASSWORD ""
grep -q "^APP_KEY=base64" .env || run php artisan key:generate --force

# Timezone from env (default Asia/Karachi)
perl -pi -e "s/'timezone' => [^,]+,/'timezone' => env('APP_TIMEZONE', 'Asia\/Karachi'),/" config/app.php

# Admin seed credentials + placeholders used in later weeks
if ! grep -q "ADMIN_EMAIL" .env.example; then
  cat >> .env.example <<'EOF'

# --- Seeded super admin (Week 1) ---
ADMIN_NAME="Super Admin"
ADMIN_EMAIL=
ADMIN_PASSWORD=

# --- Payment gateways (credentials are stored encrypted in Settings; these are only defaults) ---
JAZZCASH_MODE=sandbox
EASYPAISA_MODE=sandbox
# Card PSP: Phase 2

# --- WhatsApp (Green API) — values from console.green-api.com ---
GREEN_API_URL=https://api.green-api.com
GREEN_API_ID_INSTANCE=
GREEN_API_TOKEN_INSTANCE=
WHATSAPP_RATE_PER_MINUTE=20

# --- SMS (optional OTP fallback) ---
SMS_PROVIDER=
SMS_API_KEY=
SMS_SENDER=

# --- Object storage (staging/production) ---
# AWS_ACCESS_KEY_ID=
# AWS_SECRET_ACCESS_KEY=
# AWS_DEFAULT_REGION=
# AWS_BUCKET=
# AWS_ENDPOINT=
EOF
  sed -n '/# --- Seeded super admin/,$p' .env.example >> .env
fi
green ".env configured (DB $DB_DATABASE @ $DB_HOST:$DB_PORT, timezone Asia/Karachi)"

# ----------------------------------------------------------------------------
step "Switching tests to Pest"
if [ -f vendor/bin/pest ]; then
  green "Pest already installed"
else
  run composer remove phpunit/phpunit --dev --no-interaction || true
  run composer require pestphp/pest pestphp/pest-plugin-laravel --dev --with-all-dependencies --no-interaction \
    || fail "Pest install failed"
  [ -f tests/Pest.php ] || run ./vendor/bin/pest --init
  # Convert the default example tests to Pest style
  cat > tests/Feature/ExampleTest.php <<'EOF'
<?php

declare(strict_types=1);

it('returns a successful response', function () {
    $this->get('/')->assertOk();
});
EOF
  cat > tests/Unit/ExampleTest.php <<'EOF'
<?php

declare(strict_types=1);

it('runs the unit suite', function () {
    expect(true)->toBeTrue();
});
EOF
  green "Pest installed"
fi

# ----------------------------------------------------------------------------
step "Installing application packages"
run composer require --no-interaction -W \
  "filament/filament:^5.0" \
  laravel/horizon \
  spatie/laravel-permission \
  spatie/laravel-activitylog \
  spatie/laravel-medialibrary \
  spatie/laravel-settings \
  spatie/laravel-pdf \
  spatie/laravel-backup \
  "filament/spatie-laravel-media-library-plugin:^5.0" \
  "filament/spatie-laravel-settings-plugin:^5.0" \
  || fail "composer require (app packages) failed"
green "Filament 5, Horizon, Spatie permission/activitylog/medialibrary/settings/pdf/backup"

step "Installing dev packages"
run composer require --dev --no-interaction -W larastan/larastan knuckleswtf/scribe \
  || fail "composer require (dev packages) failed"
green "Larastan, Scribe (Pint ships with Laravel)"

step "Trying Filament Shield (optional)"
if run composer require --no-interaction bezhansalleh/filament-shield; then
  green "Filament Shield installed (configure it in Prompt 4)"
  SHIELD="installed"
else
  warn "Filament Shield has no Filament 5-compatible release yet — skipped. Prompt 4 handles the fallback."
  SHIELD="skipped"
fi

# ----------------------------------------------------------------------------
step "Running package installers"
[ -f app/Providers/Filament/AdminPanelProvider.php ] || run php artisan filament:install --panels --no-interaction \
  || fail "filament:install failed"
[ -f routes/api.php ] || run php artisan install:api --no-interaction || fail "install:api (Sanctum) failed"
[ -f config/horizon.php ] || run php artisan horizon:install || fail "horizon:install failed"

run php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" || true
run php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations" || true
run php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config" || true
run php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations" || true
run php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag="migrations" || true
run php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag="config" || true
run php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider" --tag="backup-config" || true
run php artisan vendor:publish --tag="scribe-config" || true
green "Filament panel (/admin), Sanctum API, Horizon, package configs and migrations published"

# ----------------------------------------------------------------------------
step "Creating folder structure (CLAUDE.md)"
for d in Actions Enums Events Listeners Jobs Policies Services Settings Livewire Support \
         Models/Concerns Payments/Contracts Payments/Gateways Payments/DTOs \
         Http/Controllers/Api/V1 Http/Requests/Api/V1 Http/Resources/V1; do
  mkdir -p "app/$d"
  [ -n "$(ls -A "app/$d" 2>/dev/null)" ] || touch "app/$d/.gitkeep"
done
green "app/ folders ready"

# ----------------------------------------------------------------------------
step "Quality tooling config"
cat > phpstan.neon <<'EOF'
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 6
    paths:
        - app/
        - database/
        - routes/
    excludePaths:
        - database/migrations/*
EOF

cat > pint.json <<'EOF'
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true
    }
}
EOF

php -r '
$f = "composer.json";
$j = json_decode(file_get_contents($f), true);
$j["scripts"]["lint"]  = "./vendor/bin/pint --test";
$j["scripts"]["fix"]   = "./vendor/bin/pint";
$j["scripts"]["stan"]  = "./vendor/bin/phpstan analyse --memory-limit=1G";
$j["scripts"]["check"] = ["@lint", "@stan", "@php artisan test"];
$j["name"] = "oztech/donation-app";
$j["description"] = "Donation Management System (single NGO)";
file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
'
green "phpstan.neon (level 6), pint.json (strict types), composer lint/fix/stan/check"

# ----------------------------------------------------------------------------
step "Frontend + PDF engine (npm)"
run npm install || fail "npm install failed"
if run npm install puppeteer --save-dev; then
  green "Puppeteer installed (Chromium for spatie/laravel-pdf)"
else
  warn "Puppeteer install failed — receipts PDF needs it in Week 5. Retry: cd $APP_DIR && npm i -D puppeteer"
fi
run npm run build || fail "npm run build failed"
green "Assets built"

# ----------------------------------------------------------------------------
step "Migrating database"
run php artisan migrate --force || fail "Migrations failed (check DB credentials in $APP_DIR/.env)"
green "Migrations ran on $DB_DATABASE"

step "Applying code style (strict types everywhere)"
run ./vendor/bin/pint || true
green "Pint applied"

# ----------------------------------------------------------------------------
step "Running checks"
STATUS_TEST="✘"; STATUS_STAN="✘"; STATUS_LINT="✘"
run ./vendor/bin/pint --test && STATUS_LINT="✔"
run ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress && STATUS_STAN="✔"
run php artisan test && STATUS_TEST="✔"
echo "  Pint $STATUS_LINT   Larastan $STATUS_STAN   Tests $STATUS_TEST"

# ----------------------------------------------------------------------------
cd "$ROOT"
step "Git repository"
if [ ! -d .git ]; then
  run git init -b main
fi
run git add -A
if git diff --cached --quiet; then
  green "Nothing new to commit"
else
  run git commit -m "W01-D1: project setup — Laravel 13, Filament 5, packages, docs

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" || fail "git commit failed (set git user.name / user.email)"
  green "Initial commit on main"
fi
git show-ref --quiet refs/heads/develop || run git branch develop
run git checkout develop
green "On branch develop"

# ----------------------------------------------------------------------------
echo
blue "════════════════════════════════════════════════════════════"
blue " Setup complete"
blue "════════════════════════════════════════════════════════════"
echo " App folder:      $ROOT/$APP_DIR"
echo " Admin panel:     $APP_URL/admin   (after: herd link, see below)"
echo " Filament Shield: $SHIELD"
echo " Checks:          Pint $STATUS_LINT · Larastan $STATUS_STAN · Tests $STATUS_TEST"
echo
echo " Next:"
echo "   1. cd $APP_DIR && herd link donation-app     # or: composer run dev"
echo "   2. php artisan make:filament-user            # temporary login until Prompt 4 seeds roles"
echo "   3. Add the Bitbucket remote:  git remote add origin <url> && git push -u origin main develop"
echo "   4. Open Claude Code in $ROOT and paste Prompt 1 from docs/prompts/week-01.md"
echo
echo " Full log: $LOG"
