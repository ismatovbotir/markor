<?php

namespace App\Mcp\Tools\News;

use App\Domain\Content\NewsPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get one news post with its full text in uz/ru/zh, by id or slug.')]
class GetNews extends Tool
{
    public function handle(Request $request): Response
    {
        $post = FindPost::from($request);

        return $post ? Response::json($post->toToolArray(withBody: true)) : Response::error('News post not found.');
    }

    public function schema(JsonSchema $schema): array
    {
        return FindPost::schema($schema);
    }
}
