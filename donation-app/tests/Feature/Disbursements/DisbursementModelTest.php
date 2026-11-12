<?php

declare(strict_types=1);

use App\Enums\DisbursementStatus;
use App\Enums\PayoutMethod;
use App\Models\Application;
use App\Models\Disbursement;
use App\Settings\ApprovalSettings;
use Illuminate\Support\Facades\DB;

// W10-D1 — FR-DSB-01, BR-04: the disbursement record.

it('gets a DS number and the approval rules in force when requested', function () {
    $disbursement = Disbursement::factory()->create();

    expect($disbursement->disbursement_no)->toBe('DS-'.now()->year.'-000001')
        ->and($disbursement->status)->toBe(DisbursementStatus::Requested)
        ->and($disbursement->required_approvals)->toBe(1)
        ->and($disbursement->self_approval_allowed)->toBeTrue() // on at launch
        ->and($disbursement->requested_at)->not->toBeNull();
});

it('needs more approvals at the thresholds (at or above)', function (string $amount, int $levels) {
    expect(Disbursement::factory()->create(['amount' => $amount])->required_approvals)->toBe($levels);
})->with([
    'just below second level' => ['99999.99', 1],
    'at second level' => ['100000', 2],
    'between' => ['250000', 2],
    'at trustee level' => ['500000', 3],
    'above' => ['1200000', 3],
]);

it('keeps the rules of a waiting request when settings change', function () {
    $before = Disbursement::factory()->create(['amount' => '150000']);

    $settings = app(ApprovalSettings::class);
    $settings->allow_founder_self_approval = false;
    $settings->second_level_threshold = 200000;
    $settings->save();

    $after = Disbursement::factory()->create(['amount' => '150000']);

    expect($before->fresh())
        ->required_approvals->toBe(2)
        ->self_approval_allowed->toBeTrue()
        ->and($after)
        ->required_approvals->toBe(1)
        ->self_approval_allowed->toBeFalse();
});

it('recounts the approvals needed when the amount is corrected before any decision', function () {
    $disbursement = Disbursement::factory()->create(['amount' => '50000']);

    $disbursement->update(['amount' => '120000']);

    expect($disbursement->fresh()?->required_approvals)->toBe(2);
});

it('takes the household from the application', function () {
    $application = Application::factory()->create();

    expect(Disbursement::factory()->create(['application_id' => $application->id])->beneficiary_id)->toBe($application->beneficiary_id);
});

it('is for exactly one application or project', function (array $attributes) {
    Disbursement::factory()->create($attributes);
})->with([
    'neither' => [['application_id' => null, 'project_id' => null]],
    'both' => [fn () => ['application_id' => Application::factory()->create()->id, 'project_id' => 7]],
])->throws(DomainException::class, 'one application or one project');

it('checks amount, payee, purpose and account', function (array $attributes, string $message) {
    expect(fn () => Disbursement::factory()->create($attributes))->toThrow(DomainException::class, $message);
})->with([
    'zero' => [['amount' => '0'], 'more than zero'],
    'negative' => [['amount' => '-100'], 'more than zero'],
    'three decimals' => [['amount' => '10.555'], 'more than zero'],
    'no payee' => [['payee_name' => '  '], 'who receives'],
    'no purpose' => [['purpose' => ''], 'what the money is for'],
    'wallet without number' => [['method' => PayoutMethod::Wallet, 'payee_account' => null], 'Enter the account'],
]);

it('stores the payee account encrypted, hidden and masked', function () {
    $disbursement = Disbursement::factory()->byWallet('03001234567')->create();

    expect(DB::table('disbursements')->where('id', $disbursement->id)->value('payee_account'))->not->toContain('03001234567')
        ->and($disbursement->fresh()?->payee_account)->toBe('03001234567')
        ->and($disbursement->maskedPayeeAccount())->toBe('0300•••••67')
        ->and($disbursement->toArray())->not->toHaveKey('payee_account');
});

it('never changes its number or its approval rules', function (array $change) {
    $disbursement = Disbursement::factory()->create();

    expect(fn () => $disbursement->forceFill($change)->save())->toThrow(DomainException::class);
})->with([
    'number' => [['disbursement_no' => 'DS-1999-000001']],
    'levels' => [['required_approvals' => 3]],
    'self-approval rule' => [['self_approval_allowed' => false]],
]);

it('does not let status be mass-assigned or skip steps', function () {
    $disbursement = Disbursement::factory()->create();
    $disbursement->fill(['status' => 'paid', 'is_self_approved' => true])->save();

    expect($disbursement->fresh())
        ->status->toBe(DisbursementStatus::Requested)
        ->is_self_approved->toBeFalse();

    expect(fn () => $disbursement->forceFill(['status' => DisbursementStatus::Paid])->save())
        ->toThrow(DomainException::class, 'cannot go from');
});

it('is final once paid, rejected or withdrawn', function (DisbursementStatus $final) {
    $disbursement = Disbursement::factory()->create();
    DB::table('disbursements')->where('id', $disbursement->id)->update(['status' => $final->value]);

    expect(fn () => $disbursement->fresh()?->update(['purpose' => 'Changed']))->toThrow(DomainException::class, 'cannot be changed');
})->with([DisbursementStatus::Paid, DisbursementStatus::Rejected, DisbursementStatus::Cancelled]);
