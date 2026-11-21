<?php

namespace App\Policies;

use App\Domain\Marking\Models\MarkingCode;
use App\Models\User;

class MarkingCodePolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, MarkingCode $model): bool { return $user->isAdmin() || ($user->company_id && (int) $model->company_id === (int) $user->company_id); }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, MarkingCode $model): bool { return $this->view($user, $model); }
    public function delete(User $user, MarkingCode $model): bool { return $user->isAdmin(); }
}
