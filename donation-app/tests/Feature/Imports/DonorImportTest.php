<?php

declare(strict_types=1);

use App\Enums\DonorType;
use App\Models\Donor;
use App\Services\Imports\DonorImportService;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

// W12-D5 — importing the NGO's existing donors at go-live.

function importer(): DonorImportService
{
    return app(DonorImportService::class);
}

function donorCsv(string ...$rows): string
{
    return "\xEF\xBB\xBF"."Full Name,Mobile No,E-mail,CNIC,Type,City,WhatsApp,Language\n".implode("\n", $rows)."\n";
}

it('checks the file in a dry run without saving anything', function () {
    $report = importer()->import(donorCsv('Ayesha Khan,0300-1234567,ayesha@example.com,35202-1234567-1,individual,Lahore,yes,ur'));

    expect($report['created'])->toBe(1)
        ->and($report['rows'][0]['status'])->toBe('would be created')
        ->and(Donor::query()->count())->toBe(0);
});

it('imports donors with clean details, a donor code, and WhatsApp only when agreed', function () {
    $report = importer()->import(donorCsv(
        'Ayesha  Khan,3001234567,AYESHA@example.com,3520212345671,individual,Lahore,yes,urdu',
        'Bilal Traders,0321-7654321,,,company,Karachi,no,',
        'Imran Ahmed,+44 7700 900123,,,,London,,',
    ), dryRun: false);

    expect($report['created'])->toBe(3);

    $ayesha = Donor::query()->where('email', 'ayesha@example.com')->sole();
    expect($ayesha->name)->toBe('Ayesha Khan')
        ->and($ayesha->phone)->toBe('03001234567')          // Excel had dropped the 0
        ->and($ayesha->donor_code)->toStartWith('DR')
        ->and($ayesha->maskedCnic())->toBe('35202-•••••••-1')
        ->and($ayesha->wantsWhatsApp())->toBeTrue()
        ->and($ayesha->prefersUrdu())->toBeTrue()
        ->and(Donor::query()->where('phone', '03217654321')->sole()->type)->toBe(DonorType::Corporate)
        ->and(Donor::query()->where('phone', '+447700900123')->sole()->wantsWhatsApp())->toBeFalse()
        ->and(Activity::query()->where('event', 'donors_imported')->exists())->toBeTrue();
});

it('never imports a donor twice — from the system or from the same file', function () {
    Donor::factory()->create(['phone' => '03001234567']);

    $report = importer()->import(donorCsv(
        'Ayesha Khan,0300-1234567,,,,,,',          // already in the system
        'Sara Ali,0333-1112223,sara@example.com,,,,,',
        'Sara A.,0333 1112223,,,,,,',              // same phone as line 3
    ), dryRun: false);

    expect($report['created'])->toBe(1)
        ->and($report['skipped'])->toBe(2)
        ->and(Donor::query()->count())->toBe(2);

    // Running the same file again changes nothing.
    expect(importer()->import(donorCsv('Sara Ali,0333-1112223,sara@example.com,,,,,'), dryRun: false)['created'])->toBe(0);
});

it('reports rows it cannot read, with the line number', function () {
    $report = importer()->import(donorCsv(
        ',0300-1234567,,,,,,',
        'No Contact,,,,,,,',
        'Bad Phone,042-35761234,,,,,,',
        'Bad Cnic,0300-7654321,,12345,,,,',
    ));

    expect($report['errors'])->toBe(4)
        ->and(array_column($report['rows'], 'line'))->toBe([2, 3, 4, 5])
        ->and($report['rows'][2]['reason'])->toContain('not a mobile number');
});

it('refuses a file without the needed columns', function () {
    importer()->import("Donor Ref,Amount\n1,500\n");
})->throws(DomainException::class, 'Name');

it('runs from the console: dry run first, then --commit, with a report', function () {
    Storage::fake('local');
    $file = tempnam(sys_get_temp_dir(), 'donors');
    file_put_contents($file, donorCsv('Ayesha Khan,0300-1234567,,,,,,'));

    $this->artisan('donors:import', ['file' => $file])->assertSuccessful();
    expect(Donor::query()->count())->toBe(0);

    $this->artisan('donors:import', ['file' => $file, '--commit' => true])->assertSuccessful();
    expect(Donor::query()->count())->toBe(1)
        ->and(Storage::disk('local')->files('imports'))->toHaveCount(2);

    @unlink($file);
});
