<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Marketplace;
use App\Mcp\Tools\News;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Markor Content')]
#[Version('1.1.0')]
#[Instructions(<<<'MD'
Manage content of the Markor website: news (/news) and marketplace products (/marketplace).
Every news post and product has texts in three languages:
uz (Uzbek, Latin script, required), ru (Russian) and zh (Chinese); an empty ru/zh text falls back to uz on the site.
The body is Markdown (## headings, **bold**, - lists, [links](https://…)); raw HTML is removed.
A post is visible on the site only when is_active is true and published_at is not in the future.
When a post retells news from another site, set source_name and source_url, and write the text in your own
words as a short summary — never copy the original article text.
Marketplace products: equipment and supplies (label printers, scanners, ribbons, labels) sold by conversation.
Products have NO price — never write prices or "price on request" in any text. Images are uploaded only in the admin panel.
A product's short description is at most 400 characters; its FAQ (up to 8 questions, uz/ru/zh) appears on the product page
and in search results, so answer real buyer questions with facts the owner gave you. Hide a product instead of deleting it.
Write only facts that are confirmed; no prices, client counts or words like "best".
MD)]
class ContentServer extends Server
{
    // all tools in one tools/list page (some clients don't follow the cursor)
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        News\ListNews::class,
        News\GetNews::class,
        News\CreateNews::class,
        News\UpdateNews::class,
        News\SetNewsVisibility::class,
        News\DeleteNews::class,
        Marketplace\ListCategories::class,
        Marketplace\ListOffers::class,
        Marketplace\GetOffer::class,
        Marketplace\FindIncompleteOffers::class,
        Marketplace\CreateOffer::class,
        Marketplace\UpdateOffer::class,
        Marketplace\SetOfferFaq::class,
        Marketplace\SetOfferVisibility::class,
        Marketplace\ReorderOffers::class,
        Marketplace\LinkOfferDriver::class,
    ];
}
