// Visitors browsing the website and the app catalog (no writes).
//   k6 run -e BASE_URL=https://staging.example.org tests/load/browse.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE } from './lib.js';

export const options = {
    stages: [
        { duration: '1m', target: 50 },   // a normal evening
        { duration: '3m', target: 200 },  // a Ramadan night after an appeal
        { duration: '1m', target: 0 },
    ],
    thresholds: {
        http_req_failed: ['rate<0.01'],
        'http_req_duration{kind:page}': ['p(95)<800'],
        'http_req_duration{kind:api}': ['p(95)<400'],
    },
};

const pages = ['/', '/campaigns', '/ways-to-give', '/projects', '/zakat', '/donate'];
const api = ['/api/v1/app/config', '/api/v1/campaigns', '/api/v1/funds', '/api/v1/banners'];

export default function () {
    const page = pages[Math.floor(Math.random() * pages.length)];
    check(http.get(BASE + page, { tags: { kind: 'page' } }), { 'page 200': (r) => r.status === 200 });

    const endpoint = api[Math.floor(Math.random() * api.length)];
    check(http.get(BASE + endpoint, { tags: { kind: 'api' } }), { 'api 200': (r) => r.status === 200 });

    sleep(1 + Math.random() * 2);
}
