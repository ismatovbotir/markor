<?php

namespace App\Mcp\Tools\Marketplace;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Find products that still need work: missing ru/zh name, missing description in any language, no FAQ, no image. Good starting point for "what should I fill in?".')]
class FindIncompleteOffers extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['category' => 'nullable|string|max:80', 'visible_only' => 'nullable|boolean']);
        $cat = !empty($data['category']) ? Catalog::category($data['category']) : null;
        if (!empty($data['category']) && !$cat) {
            return Response::error('Unknown category. Use list-categories.');
        }

        $rows = Catalog::offers()->with('offerCategory:id,slug')
            ->when($cat, fn ($q) => $q->where('offer_category_id', $cat->id))
            ->when($data['visible_only'] ?? false, fn ($q) => $q->where('is_active', true))
            ->orderBy('offer_category_id')->orderBy('sort_order')->get()
            ->map(function ($o) {
                $missing = [];
                foreach (['ru', 'zh'] as $l) {
                    if (blank($o->{"title_$l"})) $missing[] = "title_$l";
                }
                foreach (['uz', 'ru', 'zh'] as $l) {
                    if (blank($o->{"description_$l"})) $missing[] = "description_$l";
                }
                if (empty($o->faq)) $missing[] = 'faq';
                if (blank($o->image_path)) $missing[] = 'image (upload in admin)';

                return $missing ? ['id' => $o->id, 'slug' => $o->slug, 'category' => $o->offerCategory?->slug, 'title_uz' => $o->title_uz, 'is_active' => $o->is_active, 'missing' => $missing] : null;
            })->filter()->values();

        return Response::json(['count' => $rows->count(), 'products' => $rows->all()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category'     => $schema->string()->description('Limit to one category slug.'),
            'visible_only' => $schema->boolean()->description('Only products shown on the site.'),
        ];
    }
}
