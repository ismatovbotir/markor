<?php

namespace App\Http\Controllers\Guest;

use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferRequest;
use App\Domain\Printing\Models\LabelTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Order request for an offer item or a label size; stored and sent to the seller's email. */
class OfferRequestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'offer_id'          => 'nullable|integer|exists:offers,id',
            'label_template_id' => 'nullable|integer|exists:label_templates,id',
            'offer_category_id' => 'nullable|integer|exists:offer_categories,id', // "didn't find it?" request for a whole section
            'name'              => 'required|string|max:120',
            'phone'             => ['required', 'string', 'max:30', 'regex:/^\+?[\d\s\-()]{7,}$/'],
            'quantity'          => 'nullable|string|max:40',
            'comment'           => 'nullable|string|max:1000',
            'website'           => 'prohibited', // honeypot
        ]);
        $targets = collect(['offer_id', 'label_template_id', 'offer_category_id'])->filter(fn ($k) => filled($data[$k] ?? null));
        abort_unless($targets->count() === 1, 422); // exactly one: an item, a label size or a section

        if (filled($data['offer_id'] ?? null)) {
            $offer = Offer::with('offerCategory')->findOrFail($data['offer_id']);
            $title = $offer->title_uz;
            $category = $offer->offerCategory?->name_uz;
        } elseif (filled($data['offer_category_id'] ?? null)) {
            $category = \App\Domain\Offers\Models\OfferCategory::findOrFail($data['offer_category_id'])->name_uz;
            $title = "So'rov: {$category} (katalogda yo'q narsa)";
        } else {
            $template = LabelTemplate::findOrFail($data['label_template_id']);
            $title = "Etiketka {$template->width_mm}×{$template->height_mm} mm ({$template->name})";
            $category = 'Etiketkalar';
        }

        $offerRequest = OfferRequest::create([
            'offer_id' => $data['offer_id'] ?? null, 'label_template_id' => $data['label_template_id'] ?? null,
            'item_title' => $title, 'category' => $category, 'user_id' => $request->user()?->id,
            'name' => $data['name'], 'phone' => $data['phone'], 'quantity' => $data['quantity'] ?? null,
            'comment' => $data['comment'] ?? null, 'locale' => app()->getLocale(),
        ]);

        if ($email = config('contact.email')) {
            try {
                Mail::raw(
                    "Yangi buyurtma so'rovi #{$offerRequest->id}\n\n"
                    . "Mahsulot: {$title}\nToifa: {$category}\nIsm: {$offerRequest->name}\nTelefon: {$offerRequest->phone}\n"
                    . 'Miqdor: ' . ($offerRequest->quantity ?: '—') . "\nIzoh: " . ($offerRequest->comment ?: '—') . "\n\n"
                    . route('admin.offer-requests'),
                    fn ($m) => $m->to($email)->subject("Markor: buyurtma — {$title}")
                );
            } catch (\Throwable $e) {
                Log::warning('Order request email failed', ['id' => $offerRequest->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
