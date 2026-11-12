<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaxCertificate;
use App\Models\User;

/**
 * Tax certificates (area: receipts). Finance generates, reissues, voids and sends;
 * view-only roles can look and download. Never edited or deleted.
 */
class TaxCertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('receipts.view_any');
    }

    public function view(User $user, TaxCertificate $certificate): bool
    {
        return $user->can('receipts.view');
    }

    /** Generate, reissue, void and send. */
    public function create(User $user): bool
    {
        return $user->can('receipts.create');
    }

    public function update(User $user, TaxCertificate $certificate): bool
    {
        return false;
    }

    public function delete(User $user, TaxCertificate $certificate): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, TaxCertificate $certificate): bool
    {
        return false;
    }

    public function forceDelete(User $user, TaxCertificate $certificate): bool
    {
        return false;
    }
}
