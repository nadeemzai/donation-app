# Gateway settlement import (FR-FIN-02, Week 11 Day 4)

**Where:** Admin → Donations → Settlements → *Import settlement file* (Finance; Manager, Trustee and Auditor can view).

## What happens on import
1. Choose the gateway (JazzCash / Easypaisa), the day the payments were made, and the CSV.
2. The file is kept privately (`storage/app/private/settlements/…`). The same file can never be imported twice (SHA-256).
3. Every row is matched to our payments — by our reference first, then by the gateway's transaction id.

| Result | Meaning | What Finance does |
| --- | --- | --- |
| Matched | Same gateway, succeeded here, same amount, not settled before | Nothing |
| Amount differs | File amount ≠ our payment amount | Check with the gateway, then *Resolve* with a note |
| Not paid in our system | Gateway settled it, our payment is pending / failed / expired / needs review | Review the payment (late success), then *Match again* |
| No such payment | Reference / id unknown here | Check the merchant account; *Resolve* |
| Other gateway | The payment was made on the other gateway | *Resolve* |
| Settled twice | The payment is already on another matched row | Ask the gateway; *Resolve* |
| Unreadable row | No reference / id, or the amount can't be read | Check the file; *Resolve* |
| Not settled (ignored) | Row status is failed / reversed | Nothing |

The **mismatch report** (CSV on the settlement page) lists the flagged rows and our successful payments of that day that the file does not contain.

Reconciliation never changes payments, donations or the fund ledger. Gateway fees are recorded on the rows for reporting only.

## File formats
Columns are found by header name, so column order doesn't matter and title lines above the header are skipped. Comma, semicolon or tab separated; Excel's BOM is fine.

The real JazzCash / Easypaisa merchant report headers are **not confirmed yet** (sandbox credentials pending). When a real file arrives:
- add any unknown header names to `config/settlements.php` → `columns` (written in lower case without spaces / dashes / underscores);
- set `amounts_in_paisa` if the gateway sends amounts × 100;
- add its success status words / codes to `settled_statuses`.
