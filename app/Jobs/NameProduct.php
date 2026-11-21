<?php

namespace App\Jobs;

use App\Domain\Ai\ProductNamer;
use App\Domain\Catalog\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Cleans up a product name from the classifier (Claude, or transliteration as fallback). */
class NameProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $productId) {}

    public function handle(ProductNamer $namer): void
    {
        if ($product = Product::withoutGlobalScope('owner')->find($this->productId)) {
            $namer->name($product);
        }
    }
}
