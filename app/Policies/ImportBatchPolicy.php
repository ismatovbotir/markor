<?php

namespace App\Policies;

use App\Domain\Marking\Models\ImportBatch;
use App\Models\User;

class ImportBatchPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, ImportBatch $model): bool
    {
        return $user->isAdmin() || ($user->company_id && (int) $model->company_id === (int) $user->company_id);
    }
    /**
     * The code values (PDF, print page, PDF parts): only the company's own users.
     * The super admin manages batches (counts, money, deletion) but never sees the codes.
     */
    public function viewCodes(User $user, ImportBatch $model): bool
    {
        return !$user->isAdmin() && $user->company_id && (int) $model->company_id === (int) $user->company_id;
    }
    public function create(User $user): bool { return true; }
    public function update(User $user, ImportBatch $model): bool { return $user->isAdmin(); }
    public function delete(User $user, ImportBatch $model): bool { return $user->isAdmin(); }
}
