# Introduction

Donor-facing API for the Imdaad mobile app: catalog, donations, payments, receipts and profile.

<aside>
    <strong>Base URL</strong>: <code>localhost:8000</code>
</aside>

    Base URL: `/api/v1`. JSON only. Every response has the same envelope:

    ```json
    { "success": true, "data": {}, "message": "", "errors": null, "meta": {} }
    ```

    - **errors** is filled only on `422` (field → list of messages).
    - **meta.pagination** on lists: `current_page`, `per_page`, `total`, `last_page`, `has_more`. Ask for more with `?page=2`.
    - **Money** is `{ "amount": "5000.00", "currency": "PKR", "formatted": "Rs 5,000.00" }` — `amount` is a string.
    - **Enums** are `{ "value": "paid", "label": "Paid" }`. Times are ISO 8601 in Pakistan time.
    - Send `Accept-Language: ur` for Urdu messages. Send `X-Request-Id` and quote it when reporting a problem.
    - Too many requests → `429` with `meta.retry_after` (seconds).

    Full conventions: `docs/api-conventions.md` in the repository.

