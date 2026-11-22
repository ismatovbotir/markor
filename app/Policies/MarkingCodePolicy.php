<?php

namespace App\Policies;

use App\Domain\Marking\Models\MarkingCode;
use App\Models\User;

class MarkingCodePolicy
{
    public function viewAny(User $user): bool { return true; }
    // code values: only the company's own users — the super admin sees batches and counts, never codes
    public function view(User $user, MarkingCode $model): bool { return !$user->isAdmin() && $user->company_id && (int) $model->company_id === (int) $user->company_id; }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, MarkingCode $model): bool { return $this->view($user, $model); }
    // a single code is never deleted — only a whole batch (BatchAdminService) or annulled (status cancelled)
    public function delete(User $user, MarkingCode $model): bool { return false; }
    public function forceDelete(User $user, MarkingCode $model): bool { return false; }
}
