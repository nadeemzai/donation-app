// Shared helpers for the k6 load tests (Week 12 Day 2). Run against STAGING only.
import crypto from 'k6/crypto';

export const BASE = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/$/, '');

export function uuid() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

export function pakistaniMobile() {
    return '03' + String(Math.floor(Math.random() * 1e9)).padStart(9, '0');
}

// Same as App\Payments\Gateways\JazzCash\JazzCashHash::make().
export function jazzcashHash(fields, salt) {
    const names = Object.keys(fields)
        .filter((n) => n !== 'pp_SecureHash' && (n.startsWith('pp_') || n.startsWith('ppmpf_')))
        .filter((n) => fields[n] !== null && fields[n] !== undefined && String(fields[n]) !== '')
        .sort();
    const message = [salt, ...names.map((n) => String(fields[n]))].join('&');

    return crypto.hmac('sha256', salt, message, 'hex').toUpperCase();
}
