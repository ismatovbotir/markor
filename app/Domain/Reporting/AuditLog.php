<?php

namespace App\Domain\Reporting;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null; // created_at only

    protected $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id',
        'payload', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, string $entityType, int $entityId, array $payload = []): self
    {
        return self::create([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'payload'     => $payload,
            'ip'          => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }
}
