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

    /** A client edits ITS OWN name / SKU for a product its company has codes of (company_product_names). */
    public function editOwn(User $user, Product $model): bool
    {
        return $user->isAdmin() || ($user->company_id && \App\Domain\Marking\Models\MarkingCode::withoutGlobalScope('owner')
            ->where('product_id', $model->id)->where('company_id', $user->company_id)->exists());
    }
    public function delete(User $user, Product $model): bool { return $user->isAdmin(); }

    /**
     * Naming a product the classifier did not know. A client names it for ITS OWN company only
     * (company_product_names) and may correct that name any time; the shared name is not touched.
     */
    public function name(User $user, Product $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->company_id
            && blank($model->getOriginal('name_uz')) && blank($model->getOriginal('name_ru')) && $model->lookupStatus() === 'not_found'
            && \App\Domain\Marking\Models\MarkingCode::withoutGlobalScope('owner')
                ->where('product_id', $model->id)->where('company_id', $user->company_id)->exists();
    }
}
