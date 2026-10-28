<?php

declare(strict_types=1);

use App\Enums\FundType;
use App\Models\Campaign;
use App\Models\Fund;
use Database\Seeders\FundsSeeder;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

// FR-CMP-01, BR-01, BR-02, BR-10 — funds

it('seeds the six starting funds and can be re-run safely', function () {
    (new FundsSeeder)->run();
    (new FundsSeeder)->run();

    expect(Fund::pluck('code')->sort()->values()->all())
        ->toBe(['FIDYA', 'FITRANA', 'GENERAL', 'QURBANI', 'SADQA', 'ZAKAT']);
});

it('marks only the Zakat fund as Zakat', function () {
    (new FundsSeeder)->run();

    expect(Fund::where('is_zakat', true)->pluck('code')->all())->toBe(['ZAKAT'])
        ->and(Fund::where('code', 'QURBANI')->sole())
        ->type->toBe(FundType::Restricted)
        ->restriction->not->toBeEmpty();
});

it('does not overwrite a fund an admin already changed', function () {
    (new FundsSeeder)->run();
    Fund::where('code', 'SADQA')->sole()->update(['name' => 'Sadqa (renamed)']);

    (new FundsSeeder)->run();

    expect(Fund::where('code', 'SADQA')->sole()->name)->toBe('Sadqa (renamed)');
});

it('derives the Zakat flag from the type — it cannot be set by hand', function () {
    $fund = Fund::factory()->sadqa()->create();
    $fund->is_zakat = true;
    $fund->save();

    expect($fund->fresh()->is_zakat)->toBeFalse();

    $fund->update(['type' => FundType::Zakat]);

    expect($fund->fresh()->is_zakat)->toBeTrue();
});

it('requires a restricted fund to say what the money is for', function () {
    Fund::factory()->create(['type' => FundType::Restricted, 'restriction' => null]);
})->throws(DomainException::class);

it('does not allow the balance to be mass-assigned', function () {
    $fund = Fund::create(['code' => 'TEST', 'name' => 'Test', 'type' => FundType::General, 'balance' => '999999.00']);

    expect($fund->fresh()->balance)->toBe('0.00');
});

it('stores fund codes in capitals and keeps them unique', function () {
    expect(Fund::factory()->create(['code' => ' edu '])->code)->toBe('EDU');

    Fund::factory()->create(['code' => 'EDU']);
})->throws(QueryException::class);

it('cannot be permanently deleted while campaigns use it', function () {
    $fund = Fund::factory()->create();
    Campaign::factory()->for($fund)->create();

    $fund->forceDelete();
})->throws(QueryException::class);

it('lists active funds in display order', function () {
    Fund::factory()->create(['name' => 'B', 'sort_order' => 20]);
    Fund::factory()->create(['name' => 'A', 'sort_order' => 10]);
    Fund::factory()->inactive()->create(['name' => 'Hidden', 'sort_order' => 1]);

    expect(Fund::query()->active()->pluck('name')->all())->toBe(['A', 'B']);
});

it('records fund changes in the audit trail', function () {
    $fund = Fund::factory()->create(['name' => 'Education']);
    $fund->update(['name' => 'Education Support']);

    $activity = Activity::query()->latest('id')->firstOrFail();

    expect($activity->subject_type)->toBe(Fund::class)
        ->and($activity->attribute_changes['old']['name'])->toBe('Education')
        ->and($activity->attribute_changes['attributes']['name'])->toBe('Education Support');
});
