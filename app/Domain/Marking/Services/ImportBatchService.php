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

    /**
     * The client says which marking system a batch is for, when its codes fit several (the same
     * structure exists in Asl Belgisi and Честный ЗНАК). Each code moves to the category of the
     * other system with the same structure; the company remembers the choice for next files.
     */
    public function switchSystem(ImportBatch $batch, string $system): void
    {
        if (!in_array($system, \App\Domain\Marking\Support\MarkingSystem::ALL, true)) {
            throw new \DomainException('marking_system.unknown');
        }
        if ($batch->marking_system_detected) {
            throw new \DomainException('marking_system.fixed'); // the codes themselves tell the system
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($batch, $system) {
            $groups = \App\Domain\Catalog\Models\ProductGroup::active()->whereNotNull('structure')->get();
            $key = fn ($g) => json_encode(array_map(fn ($s) => [(string) $s['ai'], (int) $s['length']], $g->structure));
            $target = $groups->where('marking_system', $system)->keyBy($key);

            $used = MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)
                ->whereNotNull('product_group_id')->distinct()->pluck('product_group_id');
            foreach ($groups->whereIn('id', $used) as $from) {
                if (($to = $target->get($key($from))) && $to->id !== $from->id) {
                    MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)
                        ->where('product_group_id', $from->id)->update(['product_group_id' => $to->id]);
                }
            }

            $batch->update(['marking_system' => $system]);
            $batch->company?->update(['marking_system' => $system]);
            \App\Domain\Reporting\AuditLog::record('import_batch.marking_system', 'import_batch', $batch->id, ['system' => $system]);
        });
    }
}
