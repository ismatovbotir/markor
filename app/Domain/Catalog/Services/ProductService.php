<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /** A client: its company's products (owner scope); the super admin: all, optionally of one company. */
    public function paginate(string $search = '', ?int $companyId = null, int $perPage = 20): LengthAwarePaginator
    {
        return Product::with('company:id,name')
            ->withCount('markingCodes')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%$search%")
                ->orWhere('name_uz', 'like', "%$search%")
                ->orWhere('name_ru', 'like', "%$search%")
                ->orWhere('sku', 'like', "%$search%")
                ->orWhere('gtin', 'like', "%$search%")))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Super admin: one row per GTIN across all companies (products are per company; the plain gtin
     * index keeps this cheap) — how many companies have it and how many marking codes.
     */
    public function gtinAnalytics(string $search = '', int $perPage = 30): LengthAwarePaginator
    {
        return Product::withoutGlobalScope('owner')
            ->leftJoin('marking_codes', 'marking_codes.product_id', '=', 'products.id')
            ->when($search !== '', fn ($q) => $q->where('products.gtin', 'like', "%$search%"))
            ->groupBy('products.gtin')
            ->select('products.gtin',
                DB::raw('count(distinct products.company_id) as companies'),
                DB::raw('count(distinct products.id) as products'),
                DB::raw('count(marking_codes.id) as codes'),
                DB::raw('max(products.name) as sample_name'))
            ->orderByDesc('codes')
            ->paginate($perPage);
    }

    public function store(array $data): Product
    {
        $product = new Product($data);
        $product->markNamedByClient(); // typed by the client: the classifier never overwrites it

        $product->save();

        return $product;
    }

    public function update(Product $product, array $data): Product
    {
        $product->fill($data);
        if (array_key_exists('name', $data)) {
            $product->markNamedByClient();
        }
        $product->save();

        return $product->fresh();
    }

    public function destroy(Product $product): void
    {
        $product->delete();
    }
}
