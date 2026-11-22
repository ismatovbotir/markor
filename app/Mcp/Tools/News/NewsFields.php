<?php

namespace App\Mcp\Tools\News;

use Illuminate\Contracts\JsonSchema\JsonSchema;

/** Input fields shared by create-news and update-news. */
trait NewsFields
{
    protected function postFields(JsonSchema $schema, bool $requireUz): array
    {
        $fields = [];
        foreach (['uz' => 'Uzbek (Latin)', 'ru' => 'Russian', 'zh' => 'Chinese'] as $l => $lang) {
            $fields["title_$l"] = $schema->string()->max(200)->description("Title in $lang.");
            $fields["excerpt_$l"] = $schema->string()->max(300)->description("Short summary in $lang for the card and search results (max 300 chars).");
            $fields["body_$l"] = $schema->string()->max(20000)->description("Full text in $lang, Markdown.");
        }
        if ($requireUz) {
            $fields['title_uz']->required();
            $fields['body_uz']->required();
        }

        return $fields + [
            'source_name'  => $schema->string()->max(120)->description('Original publisher when the news comes from another site, e.g. "CRPT Turon".'),
            'source_url'   => $schema->string()->max(500)->description('Link to the original article (http/https).'),
            'published_at' => $schema->string()->description('Publish date/time, ISO 8601 (e.g. 2026-11-22 or 2026-11-22T10:00:00+05:00). Future date = scheduled.'),
            'is_active'    => $schema->boolean()->description('Show on the site (default true).'),
        ];
    }

    /** Only the post fields from the request; empty strings become null. */
    protected function postValues(array $input): array
    {
        $keys = array_keys(\App\Domain\Content\NewsPost::rules());

        return collect($input)->only($keys)->map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v)->all();
    }
}
