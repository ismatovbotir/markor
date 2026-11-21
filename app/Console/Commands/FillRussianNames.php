<?php

namespace App\Console\Commands;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\GtinLookup;
use Illuminate\Console\Command;

/** Fills the official Russian name (tasnif) for products that were found in the classifier but have none. */
class FillRussianNames extends Command
{
    protected $signature = 'products:fill-ru {--limit=500}';

    protected $description = 'Fill missing Russian product names from the national classifier (tasnif)';

    public function handle(GtinLookup $lookup): int
    {
        $filled = 0;
        $products = Product::withoutGlobalScopes()->where('lookup_status', 'found')
            ->where(fn ($q) => $q->whereNull('name_ru')->orWhere('name_ru', ''))->limit((int) $this->option('limit'))->get();

        foreach ($products as $product) {
            if ($ru = $lookup->nameIn($product->gtin, 'ru')) {
                $product->update(['name_ru' => mb_substr($ru, 0, 255), 'attributes' => array_merge($product->attributes ?? [], ['classifier_name_ru' => $ru])]);
                $filled++;
            }
            usleep(200_000); // be gentle with the public service
        }
        $this->info("Russian names filled: {$filled} of {$products->count()}");

        return self::SUCCESS;
    }
}
