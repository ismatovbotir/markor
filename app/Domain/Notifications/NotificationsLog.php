<?php

namespace App\Domain\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class NotificationsLog extends Model
{
    protected $table = 'notifications_log';

    protected $fillable = [
        'user_id', 'channel', 'event', 'payload', 'status', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query) { return $query->where('status', 'pending'); }
    public function scopeFailed($query)  { return $query->where('status', 'failed'); }
}
