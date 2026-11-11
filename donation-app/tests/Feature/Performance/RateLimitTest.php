<?php

declare(strict_types=1);

use App\Settings\GatewaySettings;

// W12-D2 — rate limits that must not hold back real payments, and the staging load-test pass.

beforeEach(function () {
    $s = app(GatewaySettings::class);
    $s->jazzcash_enabled = true;
    $s->jazzcash_mode = 'sandbox';
    $s->jazzcash_merchant_id = 'MC10001';
    $s->jazzcash_password = 'jc-password';
    $s->jazzcash_integrity_salt = 'rate-limit-test-salt';
    $s->save();
});

it('takes many gateway callbacks a minute from one JazzCash server', function () {
    foreach (range(1, 120) as $i) {
        $this->post('/payments/jazzcash/notify', ['pp_TxnRefNo' => 'T'.$i])->assertOk(); // unsigned: logged and ignored
    }
});

it('still limits donors hitting the return page', function () {
    foreach (range(1, 60) as $i) {
        $this->post('/payments/jazzcash/return', ['pp_TxnRefNo' => 'T1']);
    }

    $this->post('/payments/jazzcash/return', ['pp_TxnRefNo' => 'T1'])->assertStatus(429);
});

it('lets the load-test machine through on staging', function () {
    config()->set('donation.load_test_ips', ['127.0.0.1']);

    foreach (range(1, 70) as $i) {
        expect($this->post('/payments/jazzcash/return', ['pp_TxnRefNo' => 'T1'])->getStatusCode())->not->toBe(429);
    }
});

it('ignores the load-test list in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config()->set('donation.load_test_ips', ['127.0.0.1']);

    foreach (range(1, 60) as $i) {
        $this->post('/payments/jazzcash/return', ['pp_TxnRefNo' => 'T1']);
    }

    $this->post('/payments/jazzcash/return', ['pp_TxnRefNo' => 'T1'])->assertStatus(429);
});
