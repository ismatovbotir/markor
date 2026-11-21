<?php

namespace App\Domain\Ai;

/**
 * Marketplace item texts with Claude: name, short description and 3–4 FAQ entries in Uzbek (Latin),
 * Russian and Chinese, from what the super admin gives (name, category, price, link, notes).
 * The result only fills the form — the admin reviews and saves it. Returns null when AI is
 * unavailable (no key, credit finished, API down); the form then stays for manual input.
 */
class OfferWriter
{
    public function __construct(private Claude $claude) {}

    /** @param array{title:string, category:string, price:?string, url:?string, notes:?string} $input */
    public function write(array $input): ?array
    {
        $prompt = "Category: {$input['category']}\nItem name: {$input['title']}\n"
            . (filled($input['price'] ?? null) ? "Price: {$input['price']}\n" : '')
            . (filled($input['url'] ?? null) ? "Shop link: {$input['url']}\n" : '')
            . (filled($input['notes'] ?? null) ? "Facts from the seller:\n{$input['notes']}\n" : '');

        $data = $this->claude->json('offer_content', self::SYSTEM, $prompt, self::SCHEMA, 6000, 'medium');
        if (!$data) {
            return null;
        }

        $clip = fn ($v, $n) => mb_substr(trim((string) $v), 0, $n);
        $out = [];
        foreach (['uz', 'ru', 'zh'] as $l) {
            $out["title_$l"] = $clip($data["title_$l"] ?? '', 160);
            $out["description_$l"] = $clip($data["description_$l"] ?? '', 400);
        }
        $out['faq'] = collect($data['faq'] ?? [])->take(5)->map(fn ($f) => collect(['q_uz', 'a_uz', 'q_ru', 'a_ru', 'q_zh', 'a_zh'])
            ->mapWithKeys(fn ($k) => [$k => $clip($f[$k] ?? '', str_starts_with($k, 'q') ? 200 : 600)])->all())->values()->all();

        return $out;
    }

    private const SYSTEM = <<<'TXT'
You write product cards for the Marketplace of Markor, a label-printing service in Uzbekistan. The
seller sells label printers, ribbons, scanners, data terminals (PDA/TSD), labels and accessories,
and installs and services them. Buyers are importers and shops that print Asl Belgisi (DataMatrix)
marking labels.

Write in three languages: Uzbek in the LATIN alphabet (main word for a label is "etiketka"),
Russian, and Simplified Chinese.
- title: the product name, short; keep brand and model unchanged.
- description: 1–2 plain sentences (max 300 characters): what it is and what it is for in label
  printing. Concrete, no hype.
- faq: 3–4 questions a buyer really asks about THIS item (compatibility, which labels/ribbons fit,
  setup, warranty/service, delivery) with short helpful answers.

Strict rules: use only facts given in the input or generally true for this product type. Never
invent prices, specifications, warranty periods, stock or delivery times — if something matters but
is unknown, answer "please call / write to us" in that language. No words like best, №1, cheapest.
TXT;

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'title_uz' => ['type' => 'string'], 'title_ru' => ['type' => 'string'], 'title_zh' => ['type' => 'string'],
            'description_uz' => ['type' => 'string'], 'description_ru' => ['type' => 'string'], 'description_zh' => ['type' => 'string'],
            'faq' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'q_uz' => ['type' => 'string'], 'a_uz' => ['type' => 'string'],
                        'q_ru' => ['type' => 'string'], 'a_ru' => ['type' => 'string'],
                        'q_zh' => ['type' => 'string'], 'a_zh' => ['type' => 'string'],
                    ],
                    'required' => ['q_uz', 'a_uz', 'q_ru', 'a_ru', 'q_zh', 'a_zh'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['title_uz', 'title_ru', 'title_zh', 'description_uz', 'description_ru', 'description_zh', 'faq'],
        'additionalProperties' => false,
    ];
}
