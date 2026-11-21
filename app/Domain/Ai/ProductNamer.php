<?php

namespace App\Domain\Ai;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\GtinLookup;

/**
 * Product names from the tax classifier (tasnif), which come in Uzbek Cyrillic with the MXIK
 * category in front: "Сув қайнатиш учун электр чойнак: Beltop, BT-EK-5510 оқ ранг".
 *  - with Claude: a short clean name in Latin Uzbek and in Russian;
 *  - fallback (no key, credit finished, API down): the part after the category, transliterated
 *    from Cyrillic to Latin by rules.
 * Only names that nobody typed are touched (empty, or still the automatic classifier name).
 */
class ProductNamer
{
    public function __construct(private Claude $claude) {}

    public function name(Product $product): string
    {
        $source = (string) ($product->attributes['classifier_name'] ?? '');
        if ($source === '' || !$this->isAutomatic($product)) {
            return 'skipped';
        }

        $ai = $this->claude->json('product_name', self::SYSTEM, "Classifier name: {$source}\nGTIN: {$product->gtin}", self::SCHEMA, 1000);
        $uz = trim((string) ($ai['name_uz'] ?? ''));
        $ru = trim((string) ($ai['name_ru'] ?? ''));

        $attributes = $product->attributes ?? [];
        if ($uz !== '') {
            $attributes['name_source'] = 'ai';
            $product->update(['name_uz' => mb_substr($uz, 0, 255), 'name_ru' => $ru !== '' ? mb_substr($ru, 0, 255) : $product->name_ru, 'attributes' => $attributes]);
            return 'ai';
        }

        $attributes['name_source'] = 'transliterated';
        $product->update(['name_uz' => mb_substr(self::latin(GtinLookup::shortName($source)), 0, 255), 'attributes' => $attributes]);

        return 'fallback';
    }

    /**
     * A clean name for a classifier name, without saving anything: Claude (Latin Uzbek + Russian),
     * or the transliterated short name when AI is unavailable.
     * @return array{name_uz: string, name_ru: ?string, by: string}
     */
    public function suggest(string $classifierName, string $gtin): array
    {
        $ai = $this->claude->json('product_name', self::SYSTEM, "Classifier name: {$classifierName}\nGTIN: {$gtin}", self::SCHEMA, 1000);
        if (filled($ai['name_uz'] ?? null)) {
            return ['name_uz' => trim($ai['name_uz']), 'name_ru' => trim((string) ($ai['name_ru'] ?? '')) ?: null, 'by' => 'ai'];
        }

        return ['name_uz' => self::latin(GtinLookup::shortName($classifierName)), 'name_ru' => null, 'by' => 'fallback'];
    }

    /** Still the automatic name: empty, the classifier's short name, or set by this class earlier. */
    private function isAutomatic(Product $product): bool
    {
        $source = (string) ($product->attributes['classifier_name'] ?? '');

        return blank($product->name_uz)
            || $product->name_uz === GtinLookup::shortName($source)
            || in_array($product->attributes['name_source'] ?? null, ['transliterated'], true);
    }

    /** Uzbek Cyrillic → Latin (official 1995 alphabet); Latin letters, digits and signs stay as they are. */
    public static function latin(string $text): string
    {
        $map = [
            'Ш' => 'Sh', 'ш' => 'sh', 'Ч' => 'Ch', 'ч' => 'ch', 'Ғ' => "G'", 'ғ' => "g'", 'Ў' => "O'", 'ў' => "o'",
            'Ё' => 'Yo', 'ё' => 'yo', 'Ю' => 'Yu', 'ю' => 'yu', 'Я' => 'Ya', 'я' => 'ya', 'Ц' => 'S', 'ц' => 's',
            'Щ' => 'Sh', 'щ' => 'sh', 'Ъ' => "'", 'ъ' => "'", 'Ь' => '', 'ь' => '', 'Э' => 'E', 'э' => 'e', 'Ы' => 'I', 'ы' => 'i',
            'А' => 'A', 'а' => 'a', 'Б' => 'B', 'б' => 'b', 'В' => 'V', 'в' => 'v', 'Г' => 'G', 'г' => 'g', 'Д' => 'D', 'д' => 'd',
            'Ж' => 'J', 'ж' => 'j', 'З' => 'Z', 'з' => 'z', 'И' => 'I', 'и' => 'i', 'Й' => 'Y', 'й' => 'y', 'К' => 'K', 'к' => 'k',
            'Қ' => 'Q', 'қ' => 'q', 'Л' => 'L', 'л' => 'l', 'М' => 'M', 'м' => 'm', 'Н' => 'N', 'н' => 'n', 'О' => 'O', 'о' => 'o',
            'П' => 'P', 'п' => 'p', 'Р' => 'R', 'р' => 'r', 'С' => 'S', 'с' => 's', 'Т' => 'T', 'т' => 't', 'У' => 'U', 'у' => 'u',
            'Ф' => 'F', 'ф' => 'f', 'Х' => 'X', 'х' => 'x', 'Ҳ' => 'H', 'ҳ' => 'h',
        ];
        // "Е" is "Ye" at the start of a word or after a vowel, otherwise "E"
        $text = preg_replace_callback('/(^|[\s\-("«АаЕеЁёИиОоУуЎўЭэЮюЯя])([Ее])/u', fn ($m) => $m[1] . ($m[2] === 'Е' ? 'Ye' : 'ye'), $text);

        return strtr(str_replace(['Е', 'е'], ['E', 'e'], $text), $map);
    }

    private const SYSTEM = <<<'TXT'
You name products for labels in a marking (Asl Belgisi) app in Uzbekistan. The input is the official
tax-classifier name in Uzbek Cyrillic: the MXIK category, a colon, then the product (brand, model,
variant). Return a short, clean product name:
- name_uz: Uzbek in the LATIN alphabet (o', g', sh, ch), brand and model as written, product type in
  plain words, colour/volume/size kept. No category prefix, no quotes, no trailing punctuation.
- name_ru: the same in Russian.
At most 70 characters each. Do not invent details that are not in the input.
TXT;

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'name_uz' => ['type' => 'string', 'description' => 'Uzbek, Latin alphabet'],
            'name_ru' => ['type' => 'string', 'description' => 'Russian'],
        ],
        'required' => ['name_uz', 'name_ru'],
        'additionalProperties' => false,
    ];
}
