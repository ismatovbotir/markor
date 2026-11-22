<?php

namespace App\Mcp\Tools\Marketplace;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get one marketplace product in full: names and descriptions in uz/ru/zh, FAQ, link, button, linked printer drivers.')]
class GetOffer extends Tool
{
    public function handle(Request $request): Response
    {
        $offer = Catalog::find($request);

        return $offer ? Response::json($offer->load('offerCategory', 'drivers')->toToolArray(full: true)) : Response::error('Product not found.');
    }

    public function schema(JsonSchema $schema): array
    {
        return Catalog::findSchema($schema);
    }
}
