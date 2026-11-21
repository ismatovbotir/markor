<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\ProductGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductGroupService
{
    public function paginate(string $search = '', int $perPage = 20): LengthAwarePaginator
    {
        return ProductGroup::query()
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name_ru', 'like', "%$search%")
                ->orWhere('name_uz', 'like', "%$search%")->orWhere('name_zh', 'like', "%$search%")))
            ->orderBy('sort_order')->orderBy('id')
            ->paginate($perPage);
    }

    public function store(array $data): ProductGroup
    {
        return ProductGroup::create($data);
    }

    public function update(ProductGroup $group, array $data): ProductGroup
    {
        $group->update($data);
        return $group->fresh();
    }

    public function destroy(ProductGroup $group): void
    {
        $group->delete();
    }
}
