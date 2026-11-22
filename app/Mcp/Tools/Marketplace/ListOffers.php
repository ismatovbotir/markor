<?php

namespace App\Mcp\Tools\Marketplace;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List marketplace products, in site order, optionally by category, name search or visibility. Use get-offer for texts, FAQ and drivers.')]
class ListOffers extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['category' => 'nullable|string|max:80', 'search' => 'nullable|string|max:100', 'status' => 'nullable|in:all,visible,hidden', 'limit' => 'nullable|integer|min:1|max:200']);
        $q = trim((string) ($data['search'] ?? ''));
        if (!empty($data['category']) && !Catalog::category($data['category'])) {
            return Response::error('Unknown category. Use list-categories.');
        }

        $offers = Catalog::offers()->with('offerCategory:id,slug')
            ->when(!empty($data['category']), fn ($w) => $w->where('offer_category_id', Catalog::category($data['category'])->id))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title_uz', 'like', "%$q%")->orWhere('title_ru', 'like', "%$q%")->orWhere('title_zh', 'like', "%$q%")->orWhere('slug', 'like', "%$q%")))
            ->when(($data['status'] ?? 'all') !== 'all', fn ($w) => $w->where('is_active', $data['status'] === 'visible'))
            ->orderBy('offer_category_id')->orderBy('sort_order')->orderBy('id')->limit($data['limit'] ?? 100)->get();

        return Response::json(['count' => $offers->count(), 'products' => $offers->map->toToolArray()->all()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->description('Category slug from list-categories.'),
            'search'   => $schema->string()->description('Text to find in the names or slug.'),
            'status'   => $schema->string()->enum(['all', 'visible', 'hidden']),
            'limit'    => $schema->integer()->min(1)->max(200)->description('Default 100.'),
        ];
    }
}
