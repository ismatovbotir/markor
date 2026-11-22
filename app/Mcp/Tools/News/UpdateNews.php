<?php

namespace App\Mcp\Tools\News;

use App\Domain\Content\NewsPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Change a news post (by id or slug). Send only the fields to change; an empty string clears an optional field. The slug stays the same.')]
class UpdateNews extends Tool
{
    use NewsFields;

    public function handle(Request $request): Response
    {
        $post = FindPost::from($request);
        if (!$post) {
            return Response::error('News post not found.');
        }
        $values = $this->postValues($request->validate(NewsPost::rules(partial: true)));
        foreach (['title_uz', 'body_uz'] as $required) {
            if (array_key_exists($required, $values) && $values[$required] === null) {
                return Response::error("$required cannot be empty.");
            }
        }
        if (!empty($values['source_url']) && NewsPost::where('source_url', $values['source_url'])->whereKeyNot($post->id)->exists()) {
            return Response::error('Another post already has this source_url.');
        }

        $post->update($values);
        \App\Domain\Reporting\AuditLog::record('mcp.news.updated', 'news_post', $post->id, ['fields' => array_keys($values)]);

        return Response::json(['updated' => array_keys($values)] + $post->fresh()->toToolArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return FindPost::schema($schema) + $this->postFields($schema, requireUz: false);
    }
}
