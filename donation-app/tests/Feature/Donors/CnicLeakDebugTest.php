<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Resources\Donors\Pages\EditDonor;
use App\Models\Donor;
use Livewire\Livewire;

// TEMPORARY — finds where the CNIC appears in the edit page. Delete after use.

it('writes the donor edit page html for inspection', function () {
    $donor = Donor::factory()->create(['cnic' => '35202-1234567-1', 'ntn' => '7654321-8']);
    $this->actingAs(staffUser(Role::Finance));

    $html = Livewire::test(EditDonor::class, ['record' => $donor->getRouteKey()])->html();

    $out = [];
    $offset = 0;
    while (($pos = strpos($html, '35202-1234567-1', $offset)) !== false) {
        $out[] = '…'.substr($html, max(0, $pos - 600), 900).'…';
        $offset = $pos + 1;
    }

    file_put_contents(base_path('cnic-leak.txt'), count($out)." occurrence(s)\n\n".implode("\n\n==========\n\n", $out));
    file_put_contents(base_path('cnic-leak-page.html'), $html);

    expect(true)->toBeTrue();
});
