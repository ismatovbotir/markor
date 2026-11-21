<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    public function paginate(string $search = '', ?int $groupId = null, int $perPage = 20): LengthAwarePaginator
    {
        // marking_codes_count: the viewer's own codes for a client, all codes for the super admin
        return Product::with('productGroup')
            ->withCount('markingCodes')
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name_ru', 'like', "%$search%")
                ->orWhere('name_uz', 'like', "%$search%")
                ->orWhere('gtin', 'like', "%$search%")))
            ->when($groupId, fn ($q) => $q->where('product_group_id', $groupId))
            ->latest()
            ->paginate($perPage);
    }

    public function store(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->fresh();
    }

    public function destroy(Product $product): void
    {
        $product->delete();
    }
}
