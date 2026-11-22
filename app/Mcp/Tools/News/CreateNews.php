<?php

namespace App\Mcp\Tools\News;

use App\Domain\Content\NewsPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a news post. title_uz and body_uz are required; add ru and zh texts too. The address (slug) is made from title_uz and never changes. Returns the post with its public URL.')]
class CreateNews extends Tool
{
    use NewsFields;

    public function handle(Request $request): Response
    {
        $values = $this->postValues($request->validate(NewsPost::rules()));
        if (!empty($values['source_url']) && NewsPost::where('source_url', $values['source_url'])->exists()) {
            return Response::error('A post with this source_url already exists.');
        }
        $values['published_at'] ??= now();
        $values['is_active'] ??= true;
        $values['slug'] = NewsPost::freeSlug($values['title_uz']);

        $post = NewsPost::create($values);
        \App\Domain\Reporting\AuditLog::record('mcp.news.created', 'news_post', $post->id);

        return Response::json(['created' => true] + $post->toToolArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->postFields($schema, requireUz: true);
    }
}
