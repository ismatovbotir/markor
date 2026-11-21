<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marking\Models\ImportBatch;
use App\Jobs\ProcessImportBatchJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $email): User
    {
        return User::create(['name' => $email, 'email' => $email, 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
    }

    public function test_import_reuses_a_product_created_by_a_parallel_upload(): void
    {
        $ali = $this->client('ali@t.uz');
        $batch = ImportBatch::create(['uuid' => 'x', 'filename' => 'none.csv', 'original_filename' => 'a.csv', 'uploaded_by' => $ali->id, 'status' => 'pending']);
        $existing = Product::withoutGlobalScope('owner')->create(['gtin' => '04780000000028', 'is_active' => true]);

        $job = new ProcessImportBatchJob($batch);
        $id = (fn () => $this->productId('04780000000028', null))->call($job);

        $this->assertSame($existing->id, $id);
        $this->assertSame(1, Product::withoutGlobalScope('owner')->count());
    }
}
