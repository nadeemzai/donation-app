# Production setup — Week 12 Day 4

One server is enough for launch (website, admin, API, queue, database). Files referred to below are in `donation-app/deploy/` and `donation-app/.env.production.example`.

## 1. Server

| | Recommended | Why |
| --- | --- | --- |
| Size | 4 vCPU, 8 GB RAM, 80 GB SSD | PDFs (headless Chrome) and Ramadan peaks; MySQL needs RAM |
| OS | Ubuntu 24.04 LTS | Long-term security updates |
| Location | Close to Pakistan (e.g. Middle East / Singapore / local PK host) | Speed for donors |
| Backups | Provider snapshots weekly **plus** our daily off-site backups (section 5) | Two independent copies |

## 2. Install

```bash
# as root, once
apt update && apt -y upgrade
adduser --disabled-password deploy && usermod -aG www-data deploy     # deploy user, SSH keys only
apt -y install nginx mysql-server redis-server supervisor git unzip ufw fail2ban certbot python3-certbot-nginx
add-apt-repository -y ppa:ondrej/php && apt update
apt -y install php8.4-fpm php8.4-cli php8.4-mysql php8.4-redis php8.4-mbstring php8.4-xml php8.4-bcmath \
               php8.4-intl php8.4-gd php8.4-zip php8.4-curl php8.4-opcache
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt -y install nodejs
# libraries headless Chrome needs for receipt PDFs
apt -y install libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libxkbcommon0 libxcomposite1 libxdamage1 \
               libxrandr2 libgbm1 libpango-1.0-0 libasound2t64 fonts-noto-core fonts-noto-ui-core

ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw enable          # only 22, 80, 443 open
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config && systemctl reload ssh
mysql_secure_installation
```

MySQL: create database `imdaad` (utf8mb4) and user `imdaad` with a long password, rights on that database only. In `/etc/mysql/mysql.conf.d/mysqld.cnf`: `innodb_buffer_pool_size = 2G`, `slow_query_log = 1`, `long_query_time = 1`.
Redis: listens on 127.0.0.1 only (default); set `requirepass` and put it in `REDIS_PASSWORD`.

## 3. First deploy

```bash
mkdir -p /var/www/imdaad && chown deploy:www-data /var/www/imdaad
sudo -u deploy git clone <repo> /var/www/imdaad && cd /var/www/imdaad/donation-app
cp .env.production.example .env        # fill in every empty value (never paste secrets into chat)
composer install --no-dev --optimize-autoloader
                                       # (league/flysystem-aws-s3-v3 comes with composer.lock — added on the Mac in Week 12 Day 4)
php artisan key:generate               # ONCE. Copy APP_KEY to the password manager + sealed offline copy
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --force            # roles & permissions, first Super Admin (from ADMIN_*), the default funds
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache
```

Then remove `ADMIN_PASSWORD` from `.env`, log in, set up the authenticator app, create the **second Super Admin** and the staff accounts (Settings → Staff users).

Server files:

| File | Goes to | Then |
| --- | --- | --- |
| `deploy/nginx/imdaad.conf` | `/etc/nginx/sites-available/imdaad` (+ symlink in sites-enabled) | `certbot --nginx -d imdaad.org.pk -d www.imdaad.org.pk`, `nginx -t && systemctl reload nginx` |
| `deploy/php/99-imdaad.ini` | `/etc/php/8.4/fpm/conf.d/` and `/etc/php/8.4/cli/conf.d/` | `systemctl reload php8.4-fpm` |
| `deploy/supervisor/horizon.conf` | `/etc/supervisor/conf.d/` | `supervisorctl reread && supervisorctl update` |
| `deploy/cron/imdaad` | `/etc/cron.d/imdaad` | — (cron picks it up) |

PHP-FPM pool (`/etc/php/8.4/fpm/pool.d/www.conf`): `pm = dynamic`, `pm.max_children = 40`, `pm.start_servers = 8`, `pm.min_spare_servers = 4`, `pm.max_spare_servers = 12`, `pm.max_requests = 500`.

## 4. Every later deploy

On your Mac: merge `develop` into `main`, tag, push. On the server:

```bash
cd /var/www/imdaad && sudo -u www-data ./donation-app/deploy/deploy.sh
```

It shows the maintenance page (payment callbacks keep working), pulls `main`, installs, builds, migrates, caches, restarts Horizon and PHP, brings the site back and runs `security:check`. If a step fails the site stays in maintenance mode; fix and run `php artisan up`.

## 5. Backups

- **What:** the MySQL database, `.env` (APP_KEY), and `storage/app` (receipts, tax certificates, private documents and photos), in one encrypted zip (`BACKUP_ARCHIVE_PASSWORD`).
- **Where:** this server (`local`) and an off-site S3-compatible bucket (`backups` disk: Backblaze B2, Cloudflare R2, Wasabi, AWS S3…). Make the bucket private, with an access key limited to that bucket.
- **When:** daily 01:00 Pakistan time; old copies thinned out at 00:45 (all for 7 days, then daily for 16 days, weekly for 8 weeks, monthly for 4 months, yearly for 2 years); checked at 08:45 — a missing or too-old backup e-mails `OPS_ALERT_EMAIL`.
- **Restore test (before go-live, then every 3 months):**
  1. `php artisan backup:run` and `php artisan backup:list` — the new backup is on both disks.
  2. Download the zip from the bucket to a test machine; unzip with the archive password.
  3. Import the SQL dump into an empty database; copy `.env` and `storage/app`; `php artisan migrate:status`, `php artisan ledger:verify` → "Ledger OK"; open a receipt PDF.
  4. Write the date and result in the go-live checklist.

Keep `APP_KEY` and `BACKUP_ARCHIVE_PASSWORD` in the password manager **and** in a sealed offline copy: without them a backup cannot be read.

## 6. Monitoring

| What | How | Who hears |
| --- | --- | --- |
| Site up | Uptime monitor (UptimeRobot / Better Stack) on `https://imdaad.org.pk/up` and `/api/v1/health`, every 1–5 min | SMS / app push |
| Server, cron, scheduler alive | `HEARTBEAT_URL` (healthchecks.io / Better Stack heartbeat, 10-minute period) — pinged by `ops:health` only when all is well | e-mail / push when pings stop |
| Queue, failed jobs, stuck payments, payments needing review, disk space | `ops:health` every 10 min | `OPS_ALERT_EMAIL` (repeats every 2 hours while it lasts) |
| Money | `ledger:verify` nightly 01:30 | `OPS_ALERT_EMAIL` on any difference |
| Backups | `backup:monitor` daily | `OPS_ALERT_EMAIL` |
| Queue details | `https://imdaad.org.pk/horizon` (Super Admin only) | — |
| Errors | `storage/logs/laravel-YYYY-MM-DD.log` (30 days), level warning and above | — |

## 7. Live payment gateways

1. Sign the merchant agreements; receive the **live** credentials from JazzCash and Easypaisa (by their portal or e-mail to the NGO — never forward them in chat).
2. Give each gateway these addresses to register:
   - JazzCash return URL: `https://imdaad.org.pk/payments/jazzcash/return`
   - JazzCash IPN / notification URL: `https://imdaad.org.pk/payments/jazzcash/notify`
   - Easypaisa return URL: `https://imdaad.org.pk/payments/easypaisa/return` (final: `/payments/easypaisa/done`)
3. Admin → Settings → Payment gateways: enter the live merchant ID / password / integrity salt (JazzCash) and store ID / hash key / API username / password / account number (Easypaisa); set **mode = live**; enable.
4. Make one real Rs 10 donation with each gateway, check: donation Paid, receipt arrives, fund balance up, Settlements file next day matches. Refund them from the admin.

## 8. Before opening to donors

```bash
php artisan security:check     # no FAIL
php artisan ledger:verify      # Ledger OK
php artisan ops:health         # All good
php artisan backup:run && php artisan backup:list
```

Plus: restore test done (5), uptime + heartbeat monitors on (6), live gateway test donations done (7), staff sign-off received (UAT pack section 6).
