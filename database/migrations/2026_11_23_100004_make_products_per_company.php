<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products belong to a company — never shared (owner's decision 23.11.2026, docs/spec/client-products.md).
 *  - company_id + gtin is unique; a plain gtin index keeps cross-company analytics for the super admin;
 *  - one product card, no language versions: name, description, SKU, weight/volume, manufacturer,
 *    country of origin, shelf life; gtin_seq numbers the internal EAN-13 a company generates (20…);
 *  - the old shared rows are split: each company gets its own copy of every product it has codes of
 *    (its own name from company_product_names, else the shared name, else the GTIN) and its codes,
 *    batches and aggregations move to that copy. A product nobody has codes for is soft-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('name')->nullable()->after('gtin');
            $table->text('description')->nullable()->after('name');
            $table->string('weight', 50)->nullable()->after('description');
            $table->string('manufacturer')->nullable()->after('weight');
            $table->string('country', 100)->nullable()->after('manufacturer');
            $table->string('shelf_life', 50)->nullable()->after('country');
            $table->unsignedInteger('gtin_seq')->nullable()->after('shelf_life');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['gtin']);
            $table->unique(['company_id', 'gtin']);
            $table->index('gtin');
        });

        $this->split();
    }

    private function split(): void
    {
        $now = now();
        $copyColumns = ['product_group_id', 'gtin', 'name_uz', 'name_ru', 'sku', 'barcode_ean', 'attributes', 'lookup_status', 'lookup_at', 'is_active'];

        DB::table('products')->orderBy('id')->chunkById(200, function ($products) use ($now, $copyColumns) {
            foreach ($products as $p) {
                DB::transaction(function () use ($p, $now, $copyColumns) {
                    // companies with codes of this product, biggest first
                    $companies = DB::table('marking_codes')->where('product_id', $p->id)->whereNotNull('company_id')
                        ->select('company_id', DB::raw('count(*) as n'))->groupBy('company_id')->orderByDesc('n')->pluck('company_id');

                    if ($companies->isEmpty()) {
                        $legacy = DB::table('marking_codes')->where('product_id', $p->id)->exists(); // codes from before companies
                        if (!$legacy && !$p->deleted_at) {
                            DB::table('products')->where('id', $p->id)->update(['deleted_at' => $now, 'updated_at' => $now]);
                            DB::table('audit_logs')->insert(['action' => 'product.orphan_deleted', 'entity_type' => 'product', 'entity_id' => $p->id,
                                'payload' => json_encode(['gtin' => $p->gtin]), 'created_at' => $now]);
                        }
                        return;
                    }

                    foreach ($companies->values() as $i => $companyId) {
                        $own = DB::table('company_product_names')->where('company_id', $companyId)->where('product_id', $p->id)->first();
                        $values = [
                            'company_id' => $companyId,
                            'name'       => $own?->name_uz ?: $own?->name_ru ?: $p->name_uz ?: $p->name_ru ?: $p->gtin,
                            'sku'        => $own?->sku ?: $p->sku,
                            'deleted_at' => null,
                            'updated_at' => $now,
                        ];
                        if ($own && ($own->name_uz || $own->name_ru)) {
                            $values['attributes'] = json_encode(['name_by_client' => true] + (json_decode((string) $p->attributes, true) ?: []));
                        }

                        if ($i === 0) {
                            DB::table('products')->where('id', $p->id)->update($values); // the biggest owner keeps the row
                            continue;
                        }

                        $copy = collect((array) $p)->only($copyColumns)->all();
                        $newId = DB::table('products')->insertGetId($copy + $values + ['created_at' => $p->created_at ?? $now]);
                        foreach (['marking_codes', 'import_batches'] as $table) {
                            DB::table($table)->where('product_id', $p->id)->where('company_id', $companyId)->update(['product_id' => $newId]);
                        }
                        // aggregations have no company column: they follow their codes
                        DB::table('aggregations')->where('product_id', $p->id)
                            ->whereIn('id', DB::table('marking_codes')->where('product_id', $newId)->whereNotNull('aggregation_id')->select('aggregation_id'))
                            ->update(['product_id' => $newId]);
                    }
                });
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'gtin']);
            $table->dropIndex(['gtin']);
            $table->dropForeign(['company_id']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['company_id', 'name', 'description', 'weight', 'manufacturer', 'country', 'shelf_life', 'gtin_seq']);
            $table->unique('gtin'); // fails if split copies exist: merge them by hand first
        });
        // split copies can't be merged back automatically: restore the old unique index by hand if needed
    }
};
