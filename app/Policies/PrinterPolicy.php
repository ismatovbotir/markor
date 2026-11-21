<?php

namespace App\Policies;

use App\Domain\Printing\Models\Printer;
use App\Models\User;

class PrinterPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Printer $model): bool { return true; }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Printer $model): bool { return $user->isAdmin(); }
    public function delete(User $user, Printer $model): bool { return $user->isAdmin(); }
}
