<?php

namespace App\Mcp\Tools\Marketplace;

use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

/** Shared lookups for the marketplace tools. Label-template categories are managed in the admin, not here. */
class Catalog
{
    public static function categories()
    {
        return OfferCategory::query()->where(fn ($q) => $q->whereNull('kind')->orWhere('kind', '!=', 'templates'));
    }

    public static function category(?string $slug): ?OfferCategory
    {
        return $slug ? self::categories()->where('slug', $slug)->first() : null;
    }

    /** Products of the allowed categories. */
    public static function offers()
    {
        return Offer::query()->whereIn('offer_category_id', self::categories()->select('id'));
    }

    public static function find(Request $request): ?Offer
    {
        $data = $request->validate(['id' => 'nullable|integer', 'slug' => 'nullable|string|max:160|required_without:id']);

        return isset($data['id']) ? self::offers()->find($data['id']) : self::offers()->where('slug', $data['slug'])->first();
    }

    public static function findSchema(JsonSchema $schema): array
    {
        return [
            'id'   => $schema->integer()->description('Product id (from list-offers).'),
            'slug' => $schema->string()->description('Product slug, the last part of /marketplace/{slug}. Used when id is not given.'),
        ];
    }

    /** Text fields of a product: rules and schema. */
    public static function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'title_uz' => "$req|string|max:160", 'title_ru' => 'nullable|string|max:160', 'title_zh' => 'nullable|string|max:160',
            'description_uz' => 'nullable|string|max:400', 'description_ru' => 'nullable|string|max:400', 'description_zh' => 'nullable|string|max:400',
            'link' => 'nullable|url:http,https|max:500', 'button' => 'sometimes|in:order,call', 'is_active' => 'sometimes|boolean',
        ];
    }

    public static function fieldSchema(JsonSchema $schema, bool $requireUz): array
    {
        $f = [];
        foreach (['uz' => 'Uzbek (Latin)', 'ru' => 'Russian', 'zh' => 'Chinese'] as $l => $lang) {
            $f["title_$l"] = $schema->string()->max(160)->description("Product name in $lang.");
            $f["description_$l"] = $schema->string()->max(400)->description("Short description in $lang (max 400 chars): what it is and what it is for. No price.");
        }
        if ($requireUz) {
            $f['title_uz']->required();
        }

        return $f + [
            'link'      => $schema->string()->max(500)->description('Optional external link (manufacturer page or shop), http/https.'),
            'button'    => $schema->string()->enum(['order', 'call'])->description('Button on the card: order = request form (default), call = phone call.'),
            'is_active' => $schema->boolean()->description('Show on the site (default true).'),
        ];
    }

    /** Request values → offer columns; empty strings become null. */
    public static function values(array $input): array
    {
        $map = ['link' => 'url', 'button' => 'action'];
        $out = [];
        foreach (array_keys(self::rules()) as $key) {
            if (array_key_exists($key, $input)) {
                $v = $input[$key];
                $out[$map[$key] ?? $key] = is_string($v) && trim($v) === '' ? null : $v;
            }
        }

        return $out;
    }
}
