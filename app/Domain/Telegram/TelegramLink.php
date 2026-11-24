<?php

namespace App\Domain\Telegram;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TelegramLink extends Model
{
    protected $fillable = ['user_id', 'token', 'chat_id', 'tg_username', 'tg_name', 'shared_phone', 'code', 'attempts', 'status', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Seconds a cabinet code lives, and how long a just-replaced code is still accepted (typing while it changes). */
    public const CODE_TTL = 30;
    public const CODE_GRACE = 15;

    /** The open cabinet link with this code (the current one, or the one replaced a moment ago). */
    public static function findByCode(string $code): ?self
    {
        $link = static::with('user')->where('status', 'pending')->where('code', $code)->where('expires_at', '>', now())->first();
        if (!$link && ($id = Cache::get('tg-prev-code:' . $code))) {
            $link = static::with('user')->where('status', 'pending')->find($id);
        }

        return $link;
    }

    /** A new 6-digit code for the cabinet: random on the server, not used by any other open code. */
    public function renewCode(): void
    {
        if ($this->code) {
            Cache::put('tg-prev-code:' . $this->code, $this->id, now()->addSeconds(self::CODE_GRACE));
        }
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('status', 'pending')->where('code', $code)->exists() || Cache::has('tg-prev-code:' . $code));

        $this->update(['code' => $code, 'expires_at' => now()->addSeconds(self::CODE_TTL)]);
    }

    public function isOpen(): bool
    {
        return $this->expires_at->isFuture() && in_array($this->status, ['pending', 'started', 'phone_ok'], true);
    }

    /** Phones are compared by their last 9 digits (Uzbek numbers with or without +998). */
    public static function samePhone(?string $a, ?string $b): bool
    {
        $a = substr(preg_replace('/\D/', '', (string) $a), -9);
        $b = substr(preg_replace('/\D/', '', (string) $b), -9);

        return strlen($a) === 9 && $a === $b;
    }
}
