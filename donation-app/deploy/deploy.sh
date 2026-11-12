#!/usr/bin/env bash
# Deploy the latest "main" (or a release tag: BRANCH=week12) to this server (Week 12 Day 4).
#   cd /var/www/imdaad && sudo -u www-data ./donation-app/deploy/deploy.sh
# Payment callbacks keep working while the site shows its maintenance page (bootstrap/app.php).
set -euo pipefail

REPO_DIR="${REPO_DIR:-/var/www/imdaad}"
APP_DIR="$REPO_DIR/donation-app"
BRANCH="${BRANCH:-main}"
PHP_FPM="${PHP_FPM:-php8.4-fpm}"

cd "$APP_DIR"
echo "→ Maintenance mode"
php artisan down --refresh=15 --retry=60 || true

trap 'echo "✗ Deploy failed — the site is still in maintenance mode. Fix, then: php artisan up"; exit 1' ERR

echo "→ Code ($BRANCH)"
git -C "$REPO_DIR" fetch --tags origin
if git -C "$REPO_DIR" show-ref --verify --quiet "refs/tags/$BRANCH"; then
    # A release tag (also how to roll back: BRANCH=week12 ./deploy.sh)
    git -C "$REPO_DIR" checkout --detach "refs/tags/$BRANCH"
else
    git -C "$REPO_DIR" checkout "$BRANCH"
    git -C "$REPO_DIR" pull --ff-only origin "$BRANCH"
fi

echo "→ PHP packages"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "→ Front-end (also installs the Chrome used for receipt PDFs)"
npm ci --no-audit --no-fund
npm run build

echo "→ Database"
php artisan migrate --force

echo "→ Caches"
php artisan optimize
php artisan filament:optimize
php artisan storage:link >/dev/null 2>&1 || true

echo "→ Restart workers and PHP"
php artisan horizon:terminate
sudo systemctl reload "$PHP_FPM"

php artisan up
trap - ERR
echo "✓ Live: $(git -C "$REPO_DIR" describe --tags --always)"

echo "→ Security / readiness check"
php artisan security:check || echo "⚠ security:check reported problems — read the table above."
