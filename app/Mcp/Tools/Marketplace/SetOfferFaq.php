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
#[Description('Replace the whole FAQ of a product (max 8 questions). Each item needs q_uz and a_uz; add q_ru/a_ru and q_zh/a_zh. Shown on the product page and in its FAQPage search markup. Send an empty list to remove the FAQ.')]
class SetOfferFaq extends Tool
{
    public function handle(Request $request): Response
    {
        $offer = Catalog::find($request);
        if (!$offer) {
            return Response::error('Product not found.');
        }
        $rules = ['faq' => 'present|array|max:8', 'faq.*.q_uz' => 'required|string|max:200', 'faq.*.a_uz' => 'required|string|max:600'];
        foreach (['ru', 'zh'] as $l) {
            $rules["faq.*.q_$l"] = 'nullable|string|max:200';
            $rules["faq.*.a_$l"] = 'nullable|string|max:600';
        }
        $faq = collect($request->validate($rules)['faq'])
            ->map(fn ($f) => collect(['q_uz', 'a_uz', 'q_ru', 'a_ru', 'q_zh', 'a_zh'])->mapWithKeys(fn ($k) => [$k => filled($f[$k] ?? null) ? trim($f[$k]) : null])->all())
            ->values()->all();

        $offer->update(['faq' => $faq ?: null]);
        AuditLog::record('mcp.offer.faq', 'offer', $offer->id, ['questions' => count($faq)]);

        return Response::json(['questions' => count($faq), 'url' => route('marketplace.item', $offer->slug)]);
    }

    public function schema(JsonSchema $schema): array
    {
        $item = $schema->object([
            'q_uz' => $schema->string()->max(200)->required(), 'a_uz' => $schema->string()->max(600)->required(),
            'q_ru' => $schema->string()->max(200), 'a_ru' => $schema->string()->max(600),
            'q_zh' => $schema->string()->max(200), 'a_zh' => $schema->string()->max(600),
        ]);

        return Catalog::findSchema($schema) + ['faq' => $schema->array()->items($item)->max(8)->required()->description('Questions and answers, in display order.')];
    }
}
