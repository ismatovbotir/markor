<?php

namespace App\Mcp\Tools\Marketplace;

use App\Domain\Reporting\AuditLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Change a marketplace product (by id or slug). Send only the fields to change; an empty string clears an optional field. The slug stays the same. Optional "category" moves it to the end of another category.')]
class UpdateOffer extends Tool
{
    public function handle(Request $request): Response
    {
        $offer = Catalog::find($request);
        if (!$offer) {
            return Response::error('Product not found.');
        }
        $data = $request->validate(['category' => 'nullable|string|max:80'] + Catalog::rules(partial: true));
        $values = Catalog::values($data);
        if (array_key_exists('title_uz', $values) && $values['title_uz'] === null) {
            return Response::error('title_uz cannot be empty.');
        }
        if (!empty($data['category']) && $data['category'] !== $offer->offerCategory?->slug) {
            $category = Catalog::category($data['category']);
            if (!$category) {
                return Response::error('Unknown category. Use list-categories.');
            }
            $values += ['offer_category_id' => $category->id, 'category' => $category->slug, 'icon' => $category->icon,
                'sort_order' => (int) $category->offers()->max('sort_order') + 1];
        }

        $offer->update($values);
        AuditLog::record('mcp.offer.updated', 'offer', $offer->id, ['fields' => array_keys($values)]);

        return Response::json(['updated' => array_keys($values)] + $offer->fresh(['offerCategory', 'drivers'])->toToolArray(full: true));
    }

    public function schema(JsonSchema $schema): array
    {
        return Catalog::findSchema($schema)
            + ['category' => $schema->string()->description('Move to this category slug.')]
            + Catalog::fieldSchema($schema, requireUz: false);
    }
}
