<?php

namespace App\Policies;

use App\Domain\Aggregation\Models\Aggregation;
use App\Models\User;

class AggregationPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Aggregation $model): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Aggregation $model): bool { return true; }
    public function delete(User $user, Aggregation $model): bool { return $user->isAdmin(); }
    public function disaggregate(User $user, Aggregation $model): bool { return $user->isAdmin(); }
}
