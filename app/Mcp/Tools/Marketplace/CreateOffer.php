<?php

namespace App\Mcp\Tools\Marketplace;

use App\Domain\Offers\Models\Offer;
use App\Domain\Reporting\AuditLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a marketplace product in a category (added at the end). title_uz is required; write ru and zh too. The slug comes from title_uz and never changes. No price; the image is added later in the admin. Add FAQ with set-offer-faq.')]
class CreateOffer extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['category' => 'required|string|max:80'] + Catalog::rules());
        $category = Catalog::category($data['category']);
        if (!$category) {
            return Response::error('Unknown category. Use list-categories.');
        }

        $values = Catalog::values($data);
        $offer = $category->offers()->create($values + [
            'slug' => Offer::freeSlug($values['title_uz']), 'category' => $category->slug, 'icon' => $category->icon,
            'action' => $values['action'] ?? 'order', 'is_active' => $values['is_active'] ?? true,
            'sort_order' => (int) $category->offers()->max('sort_order') + 1,
        ]);
        AuditLog::record('mcp.offer.created', 'offer', $offer->id);

        return Response::json(['created' => true] + $offer->load('offerCategory', 'drivers')->toToolArray(full: true));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['category' => $schema->string()->required()->description('Category slug from list-categories.')] + Catalog::fieldSchema($schema, requireUz: true);
    }
}
