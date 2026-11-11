// The money path: start a donation (mobile API, JazzCash hosted page), then JazzCash's server
// confirms it (signed callback), then the same callback arrives again (a replay must change
// nothing). Creates real pending/paid donations — STAGING ONLY, never production.
//
//   k6 run -e BASE_URL=https://staging.example.org -e FUND_CODE=GENERAL \
//          -e JC_SALT='<sandbox integrity salt>' tests/load/donate.js
//
// The load-test machine's IP must be in LOAD_TEST_IPS on staging, or the rate limits (10 donations
// a minute per IP) stop the test after a few seconds.
import http from 'k6/http';
import { check, fail, sleep } from 'k6';
import { Trend } from 'k6/metrics';
import { BASE, jazzcashHash, pakistaniMobile, uuid } from './lib.js';

const start = new Trend('donation_start_ms', true);
const callback = new Trend('callback_ms', true);

export const options = {
    scenarios: {
        donors: {
            executor: 'ramping-arrival-rate',
            startRate: 1,
            timeUnit: '1s',
            preAllocatedVUs: 50,
            maxVUs: 200,
            stages: [
                { duration: '1m', target: 5 },   // 5 donations a second ≈ 18,000 an hour
                { duration: '3m', target: 20 },  // peak: 20 a second
                { duration: '1m', target: 0 },
            ],
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        donation_start_ms: ['p(95)<1000'],
        callback_ms: ['p(95)<500'],
        checks: ['rate>0.99'],
    },
};

const SALT = __ENV.JC_SALT;
const FUND = __ENV.FUND_CODE || 'GENERAL';

export function setup() {
    if (!SALT) {
        fail('Set JC_SALT to the sandbox JazzCash integrity salt configured on staging.');
    }
}

export default function () {
    const startResponse = http.post(`${BASE}/api/v1/donations`, JSON.stringify({
        fund_code: FUND,
        amount: String(500 + Math.floor(Math.random() * 20) * 250),
        payment_method: 'jazzcash',
        name: 'Load Test Donor',
        phone: pakistaniMobile(),
        idempotency_key: uuid(),
    }), { headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, tags: { step: 'start' } });

    start.add(startResponse.timings.duration);

    if (!check(startResponse, { 'donation started (201)': (r) => r.status === 201 })) {
        return;
    }

    const fields = startResponse.json('data.checkout.fields') || {};
    const result = {
        pp_ResponseCode: '000',
        pp_ResponseMessage: 'Thank you for Using JazzCash',
        pp_TxnRefNo: fields.pp_TxnRefNo,
        pp_Amount: fields.pp_Amount,
        pp_TxnCurrency: 'PKR',
        pp_RetreivalReferenceNo: String(Date.now()).slice(-12),
    };
    result.pp_SecureHash = jazzcashHash(result, SALT);

    sleep(Math.random() * 2); // the donor is on the JazzCash page for a moment

    const first = http.post(`${BASE}/payments/jazzcash/notify`, result, { tags: { step: 'callback' } });
    callback.add(first.timings.duration);
    check(first, { 'callback accepted': (r) => r.status === 200 });

    const replay = http.post(`${BASE}/payments/jazzcash/notify`, result, { tags: { step: 'replay' } });
    check(replay, { 'replay accepted': (r) => r.status === 200 });
}
