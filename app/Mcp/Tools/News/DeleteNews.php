<?php

namespace App\Mcp\Tools\News;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Permanently delete a news post, by id or slug. Prefer set-news-visibility(visible=false) to hide it instead.')]
class DeleteNews extends Tool
{
    public function handle(Request $request): Response
    {
        $post = FindPost::from($request);
        if (!$post) {
            return Response::error('News post not found.');
        }
        \App\Domain\Reporting\AuditLog::record('mcp.news.deleted', 'news_post', $post->id, ['fields' => $post->toToolArray()]);
        $post->delete();

        return Response::text("Deleted news post #{$post->id} ({$post->slug}).");
    }

    public function schema(JsonSchema $schema): array
    {
        return FindPost::schema($schema);
    }
}
