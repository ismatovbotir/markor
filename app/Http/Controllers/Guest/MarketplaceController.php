<?php

namespace App\Http\Controllers\Guest;

use App\Domain\Offers\Models\Offer;
use App\Http\Controllers\Controller;

/** Public page of one Marketplace item: photo, texts, price, FAQ, order/call, related items. */
class MarketplaceController extends Controller
{
    public function show(Offer $offer)
    {
        abort_unless($offer->is_active && $offer->offerCategory?->is_active, 404);

        return view('marketplace.item', [
            'offer'    => $offer,
            'category' => $offer->offerCategory,
            'related'  => Offer::where('offer_category_id', $offer->offer_category_id)->where('is_active', true)
                ->whereKeyNot($offer->id)->orderBy('sort_order')->limit(4)->get(),
            'faq'      => $offer->localizedFaq(),
            // below the same-category items: the other categories with a few items each, in smaller cards
            'others'   => \App\Domain\Offers\Models\OfferCategory::active()->whereKeyNot($offer->offer_category_id)
                ->orderBy('sort_order')->orderBy('id')
                ->with(['offers' => fn ($q) => $q->where('is_active', true)->limit(60)])->get()
                ->each(fn ($c) => $c->setRelation('offers', $c->offers->take(4)))
                ->filter(fn ($c) => $c->offers->isNotEmpty())->values(),
        ]);
    }
}
