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
        'downloads', 'last_downloaded_at', 'archived_at', 'aslbelgi_revision', 'logo_version',
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

    /**
     * Stale for this batch: the design changed, the batch's Asl Belgisi problem codes changed since,
     * or the design shows the company logo and the company changed / removed its logo since.
     */
    public function isStaleFor(ImportBatch $batch): bool
    {
        return $this->isStale()
            || (int) $this->aslbelgi_revision !== (int) $batch->aslbelgi_revision
            || ($this->template?->usesLogo() && (int) $this->logo_version !== (int) ($batch->company?->logo_version ?? 0));
    }

    public function lastLabel(): int
    {
        return $this->first_label + $this->labels - 1;
    }
}
