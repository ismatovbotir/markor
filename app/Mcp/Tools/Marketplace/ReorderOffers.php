<?php

namespace App\Mcp\Tools\Marketplace;

use App\Domain\Offers\Models\Offer;
use App\Domain\Reporting\AuditLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Set the display order of products in one category. Pass product ids in the wanted order; products not listed keep their relative order after them.')]
class ReorderOffers extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['category' => 'required|string|max:80', 'ids' => 'required|array|min:1|max:200', 'ids.*' => 'integer|distinct']);
        $category = Catalog::category($data['category']);
        if (!$category) {
            return Response::error('Unknown category. Use list-categories.');
        }
        $current = $category->offers()->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
        $unknown = array_diff($data['ids'], $current);
        if ($unknown) {
            return Response::error('Not in this category: ' . implode(', ', $unknown));
        }
        $order = array_values(array_merge($data['ids'], array_diff($current, $data['ids'])));
        DB::transaction(fn () => collect($order)->each(fn ($id, $pos) => Offer::whereKey($id)->update(['sort_order' => $pos])));
        AuditLog::record('mcp.offer.reordered', 'offer_category', $category->id, ['order' => $order]);

        return Response::json(['category' => $category->slug, 'order' => $order]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->required()->description('Category slug.'),
            'ids'      => $schema->array()->items($schema->integer())->required()->description('Product ids, first = shown first.'),
        ];
    }
}
