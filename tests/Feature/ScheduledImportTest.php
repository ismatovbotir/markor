<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marking\Services\ImportBatchService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScheduledImportTest extends TestCase
{
    use RefreshDatabase;

    private function upload(string $gtin, int $count, string $name = 'partiya_kettle.csv')
    {
        $client = User::create(['name' => 'C', 'email' => uniqid() . '@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = "01{$gtin}21" . substr(bin2hex(random_bytes(10)), 0, 20) . "\x1D91ABCD\x1D92" . substr(base64_encode(random_bytes(33)), 0, 44);
        }
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, implode("\n", $lines));
        $this->actingAs($client);
        $batch = app(ImportBatchService::class)->createFromUpload(new UploadedFile($path, $name, 'text/csv', null, true));
        auth()->logout();

        return $batch;
    }

    public function test_upload_queues_then_scheduler_loads_and_names_the_item(): void
    {
        $this->classifier = Http::response(['success' => true, 'recordTotal' => 1, 'data' => [[
            'mxik' => '08516999043100011', 'name' => 'Сув қайнатиш учун электр чойнак: Beltop, BT-EK-5510 оқ ранг',
            'label' => 2, 'usePackage' => null, 'packages' => [['nameUz' => 'дона', 'nameRu' => 'шт.', 'nameLat' => 'dona']],
        ]]]);

        $batch = $this->upload('06906974316793', 5);
        $this->assertSame('pending', $batch->fresh()->status);
        $this->assertSame('partiya_kettle.csv', $batch->original_filename);
        $this->assertFileExists(storage_path("app/imports/{$batch->filename}"));

        $this->processBatches();

        $batch->refresh();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(5, $batch->processed_rows);
        $this->assertFileDoesNotExist(storage_path("app/imports/{$batch->filename}"));

        $product = Product::withoutGlobalScope('owner')->where('gtin', '06906974316793')->firstOrFail();
        $this->assertSame('Beltop, BT-EK-5510 oq rang', $product->name_uz);
        $this->assertSame('08516999043100011', $product->attributes['mxik']);
        $this->assertSame('found', $product->attributes['lookup']);
    }

    public function test_known_product_is_not_looked_up_again_and_unknown_is_marked(): void
    {
        $this->upload('04780000000066', 2);
        $this->processBatches();
        $this->assertSame('not_found', Product::withoutGlobalScope('owner')->where('gtin', '04780000000066')->first()->attributes['lookup']);

        $this->classifier = fn () => throw new \RuntimeException('must not be called');
        $this->upload('04780000000066', 2, 'second.csv');
        $this->processBatches();
        $this->assertSame(1, Product::withoutGlobalScope('owner')->count());
    }

    public function test_classifier_down_keeps_batch_usable(): void
    {
        $this->classifier = Http::response('oops', 500);
        $batch = $this->upload('04780000000073', 3);
        $this->processBatches();

        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame('error', Product::withoutGlobalScope('owner')->first()->attributes['lookup']);
    }

    public function test_upload_modal_queues_the_file(): void
    {
        $client = User::create(['name' => 'C', 'email' => 'modal@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true,
            'company_id' => \App\Domain\Companies\Company::create(['tin' => '306000001', 'name' => 'M'])->id, 'company_role' => 'owner']);
        $this->actingAs($client);
        $file = UploadedFile::fake()->createWithContent('partiya.csv', "0104780000000202" . "21" . str_repeat('Q', 20) . "\x1D91ABCD\x1D92" . str_repeat('w', 44));

        $component = \Livewire\Livewire::test(\App\Http\Livewire\Marking\ImportBatchIndex::class)->set('csvFile', $file)->call('queueUpload')->assertHasNoErrors();
        $batch = \App\Domain\Marking\Models\ImportBatch::firstOrFail();
        $component->assertReturned($batch->id);

        // while the dialog is open the file is only on the server: nothing is loaded
        $this->assertSame('uploaded', $batch->status);
        $this->processBatches();
        $this->assertSame('uploaded', $batch->fresh()->status);
        $this->assertSame(0, \App\Domain\Marking\Models\MarkingCode::count());

        // dialog closed → queued → the loader takes it
        $component->call('releaseUpload', $batch->id);
        $this->assertSame('pending', $batch->fresh()->status);
        $this->processBatches();
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame(1, \App\Domain\Marking\Models\MarkingCode::count());

        // a batch whose dialog never closed (browser closed) is still loaded after a minute
        $file2 = UploadedFile::fake()->createWithContent('tashlab.csv', "0104780000000202" . "21" . str_repeat('Z', 20) . "\x1D91ABCD\x1D92" . str_repeat('w', 44));
        \Livewire\Livewire::test(\App\Http\Livewire\Marking\ImportBatchIndex::class)->set('csvFile', $file2)->call('queueUpload');
        $abandoned = \App\Domain\Marking\Models\ImportBatch::latest('id')->first();
        $this->processBatches();
        $this->assertSame('uploaded', $abandoned->fresh()->status);
        $this->travel(2)->minutes();
        $this->processBatches();
        $this->assertSame('completed', $abandoned->fresh()->status);

        // the modal's JavaScript must call this method, not Livewire's own $wire.upload()
        $this->assertStringContainsString('$wire.releaseUpload(id)', file_get_contents(resource_path('views/livewire/marking/import-batch-index.blade.php')));
    }
}
