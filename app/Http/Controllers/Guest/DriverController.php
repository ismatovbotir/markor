<?php

namespace App\Http\Controllers\Guest;

use App\Domain\Content\PrinterDriver;
use App\Domain\Offers\Models\Offer;
use App\Http\Controllers\Controller;

/** Public printer driver pages: a list by brand and one page per model (download link goes to the manufacturer). */
class DriverController extends Controller
{
    public function index()
    {
        return view('drivers.index', [
            'brands' => PrinterDriver::active()->with(['offers' => fn ($q) => $q->active()])->orderBy('brand')->orderBy('sort_order')->get()->groupBy('brand'),
        ]);
    }

    public function show(PrinterDriver $driver)
    {
        abort_unless($driver->is_active, 404);

        return view('drivers.show', [
            'driver'  => $driver,
            'products'=> $driver->offers()->active()->orderBy('sort_order')->get(),
            'siblings'=> PrinterDriver::active()->where('brand', $driver->brand)->whereKeyNot($driver->id)->orderBy('sort_order')->limit(6)->get(),
            // the marketplace: every category with a few random items (one row; no compatibility claim)
            'categories' => \App\Domain\Offers\Models\OfferCategory::active()->orderBy('sort_order')->orderBy('id')
                ->with(['offers' => fn ($q) => $q->where('is_active', true)])->get()
                ->each(fn ($c) => $c->setRelation('offers', $c->offers->shuffle()->take(4)->values()))
                ->filter(fn ($c) => $c->offers->isNotEmpty())->values(),
        ]);
    }
}
