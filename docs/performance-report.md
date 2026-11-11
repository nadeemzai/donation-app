# Performance report — Week 12 Day 2

**Goal:** the donate and payment-callback paths stay fast and correct at a Ramadan peak, and no
page slows down as data grows.

## What changed today

| Area | Change | Why |
| --- | --- | --- |
| N+1 queries | `tests/Feature/Performance/QueryCountTest.php` loads 5 public pages and 9 admin lists with 2 rows, then 9 rows; the query count must not grow. | Catches a relation loaded once per row, today and in every future change. |
| N+1 in development | Lazy-loaded relations are written to the log in the **local** environment (`Model::preventLazyLoading`, logging only). | Shows N+1s during development and staff testing without breaking anything. |
| Indexes | `2027_01_19_100000_add_performance_indexes`: payments (method, status, completed_at); disbursements (fund_id, status, paid_at); qurbani_bookings (status, hold_until); activity_log (created_at) and (event, created_at). | Settlement matching, fund report / project spend, the 15-minute hold expiry, the audit log screen. Existing indexes on donations, ledger, receipts, donors, beneficiaries already cover their queries. |
| Rate limit on callbacks | JazzCash server callbacks now 600/min per IP (was 60/min shared with donor returns). Donor return pages stay 60/min. | Callbacks come from a few JazzCash IPs; at a peak 60/min would have held back real payments until the 5-minute status check. Every callback is hash-checked first. |
| Load testing | k6 scripts in `tests/load/` and `LOAD_TEST_IPS` (staging only, ignored in production). | Measure the real paths without the rate limits getting in the way. |
| Production speed checks | `php artisan security:check` now also warns when config / routes / events aren't cached, settings cache or OPcache is off, or the cache isn't Redis. | One command before go-live. |

Already in place from earlier weeks: campaign totals and fund balances are stored (no SUM per page view), public pages eager-load fund and media, home-page statistics are cached 10 minutes, receipts / messages / tax certificates run on the queue.

## Targets

| Path | Target (p95) | At |
| --- | --- | --- |
| Public pages (home, campaigns, donate) | < 800 ms | 200 concurrent visitors |
| App catalog API | < 400 ms | 200 concurrent visitors |
| Start a donation (API) | < 1 s | 20 donations / second |
| JazzCash callback | < 500 ms | 20 / second, plus replays |
| Errors | < 1 % | throughout |
| Correctness | every successful callback = one paid donation, one ledger entry; `ledger:verify` clean | after the run |

## How to run the load test (staging, never production)

1. Staging matches production: same server size, Redis, Horizon running, `php artisan optimize`, `APP_DEBUG=false`.
2. Turn WhatsApp and SMS **off** in Settings, and set `MAIL_MAILER=log` — the test creates thousands of donors and receipts.
3. Put the k6 machine's public IP in `LOAD_TEST_IPS` on staging; `php artisan config:cache`.
4. Have a fund with code `GENERAL` (or pass `FUND_CODE`) and JazzCash enabled in **sandbox** mode with a test integrity salt.
5. Install k6 (`brew install k6`) and run:

```bash
k6 run -e BASE_URL=https://staging.example.org tests/load/browse.js
k6 run -e BASE_URL=https://staging.example.org -e FUND_CODE=GENERAL -e JC_SALT='<staging sandbox salt>' tests/load/donate.js
```

6. Afterwards, on staging:

```bash
php artisan ledger:verify           # must say "Ledger OK"
php artisan horizon:status          # queue drained? (Horizon dashboard: failed jobs = 0)
```

7. Remove `LOAD_TEST_IPS`, `php artisan config:cache`. Fill in the results below.

## Results (fill in after the staging run)

| Run | Date | Server | p95 | Max RPS | Errors | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| browse.js | | | page: · api: | | | |
| donate.js | | | start: · callback: | | | |
| Correctness | | | — | — | — | paid = callbacks? ledger:verify? |

## Production tuning (Week 12 Day 4 checklist)

- Every deploy: `php artisan optimize && php artisan filament:optimize && php artisan icons:cache`.
- `.env`: `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis` (or database), `SETTINGS_CACHE_ENABLED=true`.
- PHP: OPcache on (`opcache.memory_consumption=256`, `opcache.validate_timestamps=0` with a reload on deploy); PHP-FPM `pm=dynamic`, `pm.max_children` ≈ (RAM for PHP ÷ 60 MB).
- Horizon: separate supervisors so a burst of receipts never delays payment jobs (payments/high, default, receipts/notifications).
- MySQL: `innodb_buffer_pool_size` ≈ 50–70 % of a dedicated DB server's RAM; slow-query log on (> 1 s) for the first weeks.
