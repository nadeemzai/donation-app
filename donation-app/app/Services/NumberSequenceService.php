<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SequenceKey;
use App\Settings\NumberingSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

/**
 * Issues public document numbers like DN-2026-000001 (BR-04).
 *
 * - One counter row per key per calendar year (Asia/Karachi); numbers restart at 1 each year.
 * - The row is locked (SELECT … FOR UPDATE) while incrementing, so two requests can
 *   never receive the same number.
 * - If the caller's transaction rolls back, the increment rolls back too and the
 *   number is issued again later: committed numbers never repeat.
 * - Prefix and length come from NumberingSettings at the moment of issue; changing
 *   them never alters numbers already issued.
 */
class NumberSequenceService
{
    public function __construct(private readonly NumberingSettings $settings) {}

    public function next(SequenceKey $key): string
    {
        $prefix = $key->prefix($this->settings);
        $year = (int) now()->format('Y');

        $number = DB::transaction(function () use ($key, $prefix, $year): int {
            $row = $this->lockedRow($key, $year);

            if ($row === null) {
                try {
                    DB::table('number_sequences')->insert([
                        'key' => $key->value,
                        'prefix' => $prefix,
                        'year' => $year,
                        'last_number' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Another request created this year's row first — use theirs.
                }

                $row = $this->lockedRow($key, $year)
                    ?? throw new RuntimeException("Could not create number sequence [{$key->value}/{$year}].");
            }

            $next = (int) $row->last_number + 1;

            DB::table('number_sequences')
                ->where('id', $row->id)
                ->update(['last_number' => $next, 'prefix' => $prefix, 'updated_at' => now()]);

            return $next;
        });

        return sprintf('%s-%d-%s', $prefix, $year, str_pad((string) $number, $this->settings->pad_length, '0', STR_PAD_LEFT));
    }

    private function lockedRow(SequenceKey $key, int $year): ?stdClass
    {
        return DB::table('number_sequences')
            ->where('key', $key->value)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();
    }
}
