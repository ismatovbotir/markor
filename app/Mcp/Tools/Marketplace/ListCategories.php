<?php

namespace App\Mcp\Tools\Marketplace;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List marketplace categories (printers, scanners, ribbons, labels…) with names in uz/ru/zh and product counts. Use the slug as "category" in other tools.')]
class ListCategories extends Tool
{
    public function handle(Request $request): Response
    {
        $cats = Catalog::categories()->withCount(['offers', 'offers as visible_offers_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')->orderBy('id')->get();

        return Response::json($cats->map(fn ($c) => [
            'slug' => $c->slug, 'name_uz' => $c->name_uz, 'name_ru' => $c->name_ru, 'name_zh' => $c->name_zh,
            'is_active' => (bool) $c->is_active, 'products' => $c->offers_count, 'visible_products' => $c->visible_offers_count,
        ])->all());
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
