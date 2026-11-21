<?php

namespace App\Policies;

use App\Models\User;
use App\Domain\Catalog\Models\ProductGroup;

class ProductGroupPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, ProductGroup $model): bool { return true; }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, ProductGroup $model): bool { return $user->isAdmin(); }
    public function delete(User $user, ProductGroup $model): bool { return $user->isAdmin(); }
}
