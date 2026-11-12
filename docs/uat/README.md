# Staff testing (UAT) — Week 12 Day 3

The pack for testers: `Imdaad-Staff-Testing-Pack.docx` (print one per tester, or share the file).

## Coordinator: prepare the staging site

```bash
# on staging (APP_ENV=staging), never on production
php artisan migrate --force
php artisan demo:seed --donors=40          # sample donors, campaigns, donations (needs dev dependencies)
php artisan uat:accounts --domain=imdaad.org.pk
```

- `uat:accounts` prints one line per role with a new password. Hand each tester **only their line**, in person.
- Gateways: JazzCash / Easypaisa in **sandbox** mode with the sandbox keys (Settings → Payment gateways). Get test wallet numbers from the gateway's sandbox guide.
- Turn off real WhatsApp sending, or point it at a test number; `MAIL_MAILER` to a test inbox (e.g. Mailpit).
- Settlement step FIN-07: after testers have made some sandbox donations, run
  `php artisan settlements:sample 2027-01-20` (that day) and give Finance the CSV from `storage/app/private/uat/`. It matches the day's payments, with one wrong amount and one unknown row on purpose.

## During testing

- Keep the issue log (section 5 of the pack). Critical and High go to development the same day.
- Check `php artisan ledger:verify` at the end of the day — it must say "Ledger OK".

## After testing

```bash
php artisan uat:accounts --disable          # switch the test accounts off
```

Go-live needs: no open Critical / High issues and all seven roles signed off (section 6).
