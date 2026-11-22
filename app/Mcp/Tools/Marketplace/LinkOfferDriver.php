<?php

namespace App\Mcp\Tools\Marketplace;

use App\Domain\Content\PrinterDriver;
use App\Domain\Reporting\AuditLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Link or unlink a printer driver page (/drivers/{slug}) and a product, so each page links to the other. Without driver arguments it lists the available drivers.')]
class LinkOfferDriver extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['driver' => 'nullable|string|max:140', 'linked' => 'nullable|boolean']);
        if (empty($data['driver'])) {
            return Response::json(PrinterDriver::orderBy('brand')->orderBy('sort_order')->get()->map(fn ($d) => ['slug' => $d->slug, 'name' => $d->name(), 'is_active' => $d->is_active])->all());
        }
        $offer = Catalog::find($request);
        if (!$offer) {
            return Response::error('Product not found.');
        }
        $driver = PrinterDriver::where('slug', $data['driver'])->first();
        if (!$driver) {
            return Response::error('Unknown driver slug. Call this tool without "driver" to list them.');
        }
        ($data['linked'] ?? true) ? $offer->drivers()->syncWithoutDetaching([$driver->id]) : $offer->drivers()->detach($driver->id);
        AuditLog::record(($data['linked'] ?? true) ? 'mcp.offer.driver_linked' : 'mcp.offer.driver_unlinked', 'offer', $offer->id, ['driver' => $driver->slug]);

        return Response::json(['product' => $offer->slug, 'drivers' => $offer->drivers()->get()->map(fn ($d) => $d->slug)->all()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return Catalog::findSchema($schema) + [
            'driver' => $schema->string()->description('Driver slug, e.g. zebra-zd421. Leave out to list drivers.'),
            'linked' => $schema->boolean()->description('true = link (default), false = unlink.'),
        ];
    }
}
