<?php

namespace App\Policies;

use App\Domain\Catalog\Models\Product;
use App\Models\User;

/** Products are one shared catalogue (one per GTIN): clients view, the super admin edits. */
class ProductPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Product $model): bool { return true; }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Product $model): bool { return $user->isAdmin(); }
    public function delete(User $user, Product $model): bool { return $user->isAdmin(); }

    /** Filling in the name of a product the classifier did not know (only while it has no name). */
    public function name(User $user, Product $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $model->needsName() && $user->company_id
            && \App\Domain\Marking\Models\MarkingCode::withoutGlobalScope('owner')
                ->where('product_id', $model->id)->where('company_id', $user->company_id)->exists();
    }
}
