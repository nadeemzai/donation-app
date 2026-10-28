<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Audit trail for a model (FR-AUD-01): who changed what, with before/after values.
 *
 * Logs the model's fillable fields, only when they change. Every hidden
 * attribute (passwords, CNIC/NTN, two-factor secrets…) is excluded, so a
 * sensitive field never reaches the activity log.
 */
trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('audit')
            ->logFillable()
            ->logExcept(array_values(array_unique([...$this->getHidden(), 'password', 'remember_token'])))
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
