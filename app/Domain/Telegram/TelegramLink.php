<?php

namespace App\Domain\Telegram;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

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
