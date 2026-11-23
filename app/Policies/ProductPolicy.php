<?php

namespace App\Policies;

use App\Domain\Catalog\Models\Product;
use App\Models\User;

/**
 * Products belong to a company and are never shared (docs/spec/client-products.md).
 *  - a client creates, edits and prints its company's products; deletes one only while it has no marking codes;
 *  - the super admin sees every company's products (with the owner) and GTIN analytics — read-only.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin() || (bool) $user->company_id; }

    public function view(User $user, Product $model): bool { return $user->isAdmin() || $this->owns($user, $model); }

    public function create(User $user): bool { return !$user->isAdmin() && (bool) $user->company_id; }

    public function update(User $user, Product $model): bool { return $this->owns($user, $model); }

    /** Older name kept for callers: editing the company's own card. */
    public function editOwn(User $user, Product $model): bool { return $this->owns($user, $model); }

    /** Naming an item from the import page (unknown to the classifier). */
    public function name(User $user, Product $model): bool { return $this->owns($user, $model); }

    /** Labels of this product (no marking code; the code area shows the Markor logo). */
    public function printLabel(User $user, Product $model): bool { return $this->owns($user, $model); }

    public function delete(User $user, Product $model): bool
    {
        return $this->owns($user, $model)
            && !\App\Domain\Marking\Models\MarkingCode::withoutGlobalScope('owner')->where('product_id', $model->id)->exists();
    }

    private function owns(User $user, Product $model): bool
    {
        return !$user->isAdmin() && $user->company_id && $model->company_id === $user->company_id;
    }
}
