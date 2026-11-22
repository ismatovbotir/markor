<?php

namespace App\Mcp\Tools\News;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Show (publish) or hide a news post on the site, by id or slug.')]
class SetNewsVisibility extends Tool
{
    public function handle(Request $request): Response
    {
        $post = FindPost::from($request);
        if (!$post) {
            return Response::error('News post not found.');
        }
        $visible = (bool) $request->validate(['visible' => 'required|boolean'])['visible'];
        $post->update(['is_active' => $visible] + ($visible && !$post->published_at ? ['published_at' => now()] : []));
        \App\Domain\Reporting\AuditLog::record('mcp.' . ($visible ? 'news.shown' : 'news.hidden'), 'news_post', $post->id);

        return Response::json($post->fresh()->toToolArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return FindPost::schema($schema) + ['visible' => $schema->boolean()->required()->description('true = show on the site, false = hide.')];
    }
}
