<?php

namespace App\Domain\Marking\Services;

use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Jobs\ProcessImportBatchJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportBatchService
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return ImportBatch::with(['uploader:id,name,email', 'product'])
            ->withCount('markingCodes')
            ->addSelect(['products_count' => MarkingCode::selectRaw('count(distinct product_id)')
                ->whereColumn('import_batch_id', 'import_batches.id')])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Saves the file and creates the batch record only — no codes are loaded here.
     * $status: 'pending' = queued for loading now; 'uploaded' = waits until the upload dialog
     * releases it (see release()), so loading never runs while the client is still in the dialog.
     */
    public function createFromUpload(UploadedFile $file, ?int $productId = null, string $status = 'pending'): ImportBatch
    {
        $uuid     = (string) Str::uuid();
        $filename = "$uuid.csv";

        // The file stays in storage/app/imports/ until its codes are loaded, then it is deleted.
        $importsPath = storage_path('app/imports');
        if (!is_dir($importsPath)) {
            mkdir($importsPath, 0755, true);
        }
        // copy, not move(): browser uploads arrive as Livewire temporary files, which move() rejects
        if (!copy($file->getRealPath(), "$importsPath/$filename")) {
            throw new \RuntimeException('Could not store the uploaded file');
        }
        if (method_exists($file, 'delete')) {
            $file->delete(); // Livewire temporary upload
        }

        $batch = ImportBatch::create([
            'uuid'              => $uuid,
            'filename'          => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'uploaded_by'       => Auth::id(),
            'company_id'        => Auth::user()?->company_id,
            'product_id'        => $productId,
            'status'            => $status,
        ]);

        // The scheduler (batches:process) picks queued batches up and loads them.
        return $batch;
    }

    /** Upload dialog closed: the batch joins the loading queue. */
    public function release(int $batchId): void
    {
        ImportBatch::whereKey($batchId)->where('status', 'uploaded')->where('uploaded_by', Auth::id())->update(['status' => 'pending']);
    }

    /** Same as an upload, for a file that arrived another way (Telegram bot). */
    public function createFromContents(int $userId, string $originalName, string $contents, string $source = 'telegram'): ImportBatch
    {
        $uuid = (string) Str::uuid();
        $importsPath = storage_path('app/imports');
        if (!is_dir($importsPath)) {
            mkdir($importsPath, 0755, true);
        }
        file_put_contents("$importsPath/$uuid.csv", $contents);

        return ImportBatch::withoutGlobalScope('owner')->create([
            'uuid' => $uuid, 'filename' => "$uuid.csv", 'original_filename' => $originalName,
            'uploaded_by' => $userId, 'company_id' => \App\Models\User::find($userId)?->company_id, 'status' => 'pending', 'source' => $source,
        ]);
    }
}
