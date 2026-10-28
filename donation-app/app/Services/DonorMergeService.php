<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Donor;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds and merges duplicate donor records (FR-DNR-04).
 *
 * Merging keeps the survivor's own details, fills its empty fields from the
 * duplicate, moves everything the duplicate owns to the survivor, then archives
 * the duplicate with a pointer to the survivor. Nothing is erased.
 */
class DonorMergeService
{
    /** Fields copied to the survivor only when the survivor's own value is empty. */
    private const FILL_FIELDS = ['email', 'phone', 'cnic', 'ntn', 'city'];

    /**
     * Other active donors that may be the same person: same name, ignoring case
     * and extra spaces. Same city first.
     *
     * @return Collection<int, Donor>
     */
    public function possibleDuplicatesOf(Donor $donor, int $limit = 10): Collection
    {
        return Donor::query()
            ->whereKeyNot($donor->getKey())
            ->whereNull('merged_into_id')
            ->whereRaw('LOWER(TRIM(name)) = ?', [self::normalizeName($donor->name)])
            ->orderByRaw('CASE WHEN city = ? THEN 0 ELSE 1 END', [$donor->city ?? ''])
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Merge $duplicate into $survivor. Returns the updated survivor.
     *
     * @throws DomainException
     */
    public function merge(Donor $duplicate, Donor $survivor): Donor
    {
        if ($duplicate->is($survivor)) {
            throw new DomainException('A donor cannot be merged into itself.');
        }

        return DB::transaction(function () use ($duplicate, $survivor): Donor {
            // Include archived records, so the message below can say why they're refused.
            /** @var Donor $duplicate */
            $duplicate = Donor::withTrashed()->lockForUpdate()->findOrFail($duplicate->getKey());
            /** @var Donor $survivor */
            $survivor = Donor::withTrashed()->lockForUpdate()->findOrFail($survivor->getKey());

            if ($duplicate->isMerged() || $survivor->isMerged()) {
                throw new DomainException('One of these donors has already been merged.');
            }

            if ($duplicate->trashed() || $survivor->trashed()) {
                throw new DomainException('Archived donors cannot be merged. Restore the donor first.');
            }

            // 1. Take the duplicate's details the survivor is missing. Clear them on the
            //    duplicate first — email, phone and CNIC are unique across all records.
            $take = [];
            foreach (self::FILL_FIELDS as $field) {
                if (blank($survivor->{$field}) && filled($duplicate->{$field})) {
                    $take[$field] = $duplicate->{$field};
                }
            }

            $passwordHash = $survivor->password === null ? $duplicate->getRawOriginal('password') : null;

            $duplicate->forceFill([
                'email' => null,
                'phone' => null,
                'cnic' => null,
                'merged_into_id' => $survivor->getKey(),
                'merged_at' => now(),
            ])->save();

            $survivor->forceFill($take);
            if (is_string($passwordHash) && $passwordHash !== '') {
                $survivor->forceFill(['password' => $passwordHash]); // already hashed — not re-hashed
            }
            $survivor->save();

            // 2. Move everything the duplicate owns.
            $this->moveRelatedRecords($duplicate, $survivor);

            // 3. Archive the duplicate; it can no longer log in.
            $duplicate->tokens()->delete();
            $duplicate->delete();

            activity('audit')
                ->performedOn($survivor)
                ->event('merged')
                ->withProperties([
                    'duplicate_id' => $duplicate->getKey(),
                    'duplicate_code' => $duplicate->donor_code,
                    'fields_taken' => array_keys($take),
                ])
                ->log("Merged duplicate donor {$duplicate->donor_code} into {$survivor->donor_code}");

            return $survivor->refresh();
        });
    }

    /**
     * Re-point the duplicate's records to the survivor.
     *
     * Week 3 onwards: add donations, recurring plans, receipts, tax certificates and
     * Qurbani bookings here — every table with a donor_id must be moved.
     */
    protected function moveRelatedRecords(Donor $duplicate, Donor $survivor): void
    {
        // No donor-owned records exist yet (Week 2).
    }

    public static function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
    }
}
