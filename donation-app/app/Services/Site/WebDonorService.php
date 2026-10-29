<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Enums\DonorType;
use App\Models\Donor;
use App\Support\Cnic;
use App\Support\PakistanPhone;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Finds or creates the donor behind a website donation (Week 6 Day 2).
 *
 * The visitor is NOT logged in, so an existing donor record is never changed from
 * here: anyone can type someone else's phone number. Changing the email, phone or
 * CNIC of an existing donor would let a stranger take over their receipts and, from
 * Day 4, their portal login. Differences are written to the donation's staff note
 * instead, so Finance can check them.
 *
 * Matching: phone first, then email. A merged duplicate leads to the record it was
 * merged into. An archived donor can't give online (staff archived them on purpose).
 */
class WebDonorService
{
    /**
     * @param  array{name: string, phone: string, email?: string|null, cnic?: string|null, whatsapp_opt_in?: bool}  $details
     * @return array{donor: Donor, notes: list<string>}
     */
    public function resolve(array $details, string $locale = 'en'): array
    {
        $phone = (string) PakistanPhone::normalize($details['phone']);
        $email = blank($details['email'] ?? null) ? null : strtolower(trim((string) $details['email']));
        $cnic = blank($details['cnic'] ?? null) ? null : Cnic::normalize((string) $details['cnic']);
        $name = trim((string) preg_replace('/\s+/u', ' ', $details['name']));

        $existing = $this->find($phone, $email);

        if ($existing !== null) {
            return ['donor' => $existing, 'notes' => $this->differences($existing, $name, $phone, $email, $cnic)];
        }

        $notes = [];

        if ($cnic !== null && Donor::withTrashed()->where('cnic_hash', Cnic::hash($cnic))->exists()) {
            $notes[] = 'The CNIC given on the website belongs to another donor record, so it was not saved. Check for a duplicate donor.';
            $cnic = null;
        }

        try {
            $donor = Donor::query()->create([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'cnic' => $cnic !== null ? self::formatCnic($cnic) : null,
                'type' => DonorType::Individual,
                'preferences' => [
                    'language' => $locale === 'ur' ? 'ur' : 'en',
                    'whatsapp_opt_in' => (bool) ($details['whatsapp_opt_in'] ?? false),
                ],
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // The same person submitted twice at the same moment; the other request created them.
            $donor = $this->find($phone, $email) ?? throw $e;
        }

        return ['donor' => $donor, 'notes' => $notes];
    }

    private function find(string $phone, ?string $email): ?Donor
    {
        $donor = Donor::withTrashed()->where('phone', $phone)->first()
            ?? ($email !== null ? Donor::withTrashed()->where('email', $email)->first() : null);

        // Follow merges to the surviving record.
        for ($hops = 0; $donor !== null && $donor->merged_into_id !== null && $hops < 5; $hops++) {
            $donor = Donor::withTrashed()->find($donor->merged_into_id);
        }

        if ($donor !== null && $donor->trashed()) {
            throw new DomainException('We could not take this donation online with these details. Please contact us and we will help.');
        }

        return $donor;
    }

    /**
     * @return list<string>
     */
    private function differences(Donor $donor, string $name, string $phone, ?string $email, ?string $cnic): array
    {
        $notes = [];

        if (mb_strtolower($donor->name) !== mb_strtolower($name)) {
            $notes[] = "Name given on the website: {$name}";
        }

        if ($donor->phone !== $phone) {
            $notes[] = "Phone given on the website: {$phone}";
        }

        if ($email !== null && $donor->email !== $email) {
            $notes[] = "Email given on the website: {$email}";
        }

        if ($cnic !== null && ($donor->cnic_hash === null || $donor->cnic_hash !== Cnic::hash($cnic))) {
            $notes[] = 'A CNIC ending '.substr($cnic, -4).' was given on the website but is not on the donor record. Confirm it with the donor before adding it.';
        }

        return $notes;
    }

    private static function formatCnic(string $digits): string
    {
        return substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12);
    }
}
