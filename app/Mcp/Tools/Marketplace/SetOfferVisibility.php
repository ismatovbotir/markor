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
#[Description('Show or hide a marketplace product on the site (by id or slug). Hiding keeps its address, orders and banners intact.')]
class SetOfferVisibility extends Tool
{
    public function handle(Request $request): Response
    {
        $offer = Catalog::find($request);
        if (!$offer) {
            return Response::error('Product not found.');
        }
        $visible = (bool) $request->validate(['visible' => 'required|boolean'])['visible'];
        $offer->update(['is_active' => $visible]);
        AuditLog::record($visible ? 'mcp.offer.shown' : 'mcp.offer.hidden', 'offer', $offer->id);

        return Response::json($offer->fresh('offerCategory')->toToolArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return Catalog::findSchema($schema) + ['visible' => $schema->boolean()->required()];
    }
}
