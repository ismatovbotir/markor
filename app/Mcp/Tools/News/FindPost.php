<?php

namespace App\Mcp\Tools\News;

use App\Domain\Content\NewsPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

/** Finds the post a tool call is about: by "id" or by "slug". */
class FindPost
{
    public static function from(Request $request): ?NewsPost
    {
        $data = $request->validate(['id' => 'nullable|integer', 'slug' => 'nullable|string|max:160|required_without:id']);

        return isset($data['id']) ? NewsPost::find($data['id']) : NewsPost::where('slug', $data['slug'])->first();
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'id'   => $schema->integer()->description('Post id (from list-news).'),
            'slug' => $schema->string()->description('Post slug, the last part of its address /news/{slug}. Used when id is not given.'),
        ];
    }
}
