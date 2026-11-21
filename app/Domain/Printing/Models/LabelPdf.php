<?php

namespace App\Domain\Printing\Models;

use App\Domain\Marking\Models\ImportBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LabelPdf extends Model
{
    protected $fillable = [
        'import_batch_id', 'label_template_id', 'part', 'parts', 'first_label', 'company_id', 'created_by', 'labels', 'size_bytes',
        'local_path', 'telegram_file_id', 'telegram_file_unique_id', 'telegram_chat_id',
        'downloads', 'last_downloaded_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return ['last_downloaded_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function batch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function template()
    {
        return $this->belongsTo(LabelTemplate::class, 'label_template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The design (or its part size) was changed after this PDF was made. */
    public function isStale(): bool
    {
        return $this->template && $this->template->updated_at && $this->template->updated_at->gt($this->updated_at);
    }

    public function lastLabel(): int
    {
        return $this->first_label + $this->labels - 1;
    }
}
