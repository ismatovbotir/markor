<?php

namespace App\Http\Controllers\Guest;

use App\Domain\Content\NewsPost;
use App\Http\Controllers\Controller;

/** Public news: a list (newest first) and one page per post. */
class NewsController extends Controller
{
    public function index()
    {
        return view('news.index', ['posts' => NewsPost::published()->orderByDesc('published_at')->paginate(12)]);
    }

    public function show(NewsPost $post)
    {
        abort_unless($post->isPublished(), 404);

        return view('news.show', [
            'post'   => $post,
            'others' => NewsPost::published()->whereKeyNot($post->id)->orderByDesc('published_at')->limit(3)->get(),
        ]);
    }
}
