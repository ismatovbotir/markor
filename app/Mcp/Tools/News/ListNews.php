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
#[Description('List news posts, newest first, with titles in uz/ru/zh, source and visibility. Use get-news for the full text.')]
class ListNews extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|in:all,public,hidden', 'limit' => 'nullable|integer|min:1|max:100']);
        $q = trim((string) ($data['search'] ?? ''));

        $posts = NewsPost::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('title_uz', 'like', "%$q%")->orWhere('title_ru', 'like', "%$q%")->orWhere('title_zh', 'like', "%$q%")))
            ->when(($data['status'] ?? 'all') === 'public', fn ($query) => $query->published())
            ->when(($data['status'] ?? 'all') === 'hidden', fn ($query) => $query->where(fn ($w) => $w->where('is_active', false)->orWhereNull('published_at')->orWhere('published_at', '>', now())))
            ->orderByDesc('published_at')->orderByDesc('id')->limit($data['limit'] ?? 30)->get();

        return Response::json(['count' => $posts->count(), 'posts' => $posts->map->toToolArray()->all()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Text to find in the titles.'),
            'status' => $schema->string()->enum(['all', 'public', 'hidden'])->description('public = visible on the site now; hidden = switched off or scheduled.'),
            'limit'  => $schema->integer()->min(1)->max(100)->description('Max posts to return (default 30).'),
        ];
    }
}
