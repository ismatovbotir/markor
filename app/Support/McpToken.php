<?php

namespace App\Support;

use App\Domain\Settings\Setting;
use Illuminate\Support\Str;

/**
 * Secret for the content MCP server (/mcp/content). Only its SHA-256 hash is stored (settings key mcp.news_token),
 * the token itself is shown once when created. A new token replaces the old one at once.
 */
class McpToken
{
    private const KEY = 'mcp.news_token';

    public static function create(): string
    {
        $token = 'mkr_' . Str::random(48);
        Setting::put([self::KEY => hash('sha256', $token), self::KEY . '_at' => now()->toDateTimeString()]);

        return $token;
    }

    public static function revoke(): void
    {
        Setting::put([self::KEY => null, self::KEY . '_at' => null]);
    }

    public static function exists(): bool
    {
        return filled(Setting::all_()[self::KEY] ?? null);
    }

    public static function createdAt(): ?string
    {
        return Setting::all_()[self::KEY . '_at'] ?? null;
    }

    public static function check(string $token): bool
    {
        $hash = Setting::all_()[self::KEY] ?? null;

        return filled($hash) && $token !== '' && hash_equals($hash, hash('sha256', $token));
    }
}
