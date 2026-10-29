<?php

declare(strict_types=1);

use App\Support\AmountInWords;

// FR-RCP-01 — amount in words on receipts, Pakistani style (lakh / crore)

it('writes rupee amounts in words', function (string $amount, string $words) {
    expect(AmountInWords::rupees($amount))->toBe($words);
})->with([
    ['1', 'One Rupee Only'],
    ['15', 'Fifteen Rupees Only'],
    ['100', 'One Hundred Rupees Only'],
    ['1000', 'One Thousand Rupees Only'],
    ['2500.00', 'Two Thousand Five Hundred Rupees Only'],
    ['12500.50', 'Twelve Thousand Five Hundred Rupees and Fifty Paisa Only'],
    ['99999', 'Ninety Nine Thousand Nine Hundred Ninety Nine Rupees Only'],
    ['100000', 'One Lakh Rupees Only'],
    ['1500000', 'Fifteen Lakh Rupees Only'],
    ['2550750', 'Twenty Five Lakh Fifty Thousand Seven Hundred Fifty Rupees Only'],
    ['10000000', 'One Crore Rupees Only'],
    ['123456789.01', 'Twelve Crore Thirty Four Lakh Fifty Six Thousand Seven Hundred Eighty Nine Rupees and One Paisa Only'],
    ['1000000000', 'One Arab Rupees Only'],
    ['0.75', 'Seventy Five Paisa Only'],
    ['0', 'Zero Rupees Only'],
]);

it('refuses amounts that are not plain decimals', function (string $amount) {
    AmountInWords::rupees($amount);
})->with(['-5', '1,000', '10.555', 'abc'])->throws(InvalidArgumentException::class);
