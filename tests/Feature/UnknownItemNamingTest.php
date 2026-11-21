<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Companies\Company;
use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Marking\Services\ImportBatchService;
use App\Http\Livewire\Marking\ImportBatchIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class UnknownItemNamingTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $tin): User
    {
        $company = Company::create(['tin' => $tin, 'name' => "C$tin"]);

        return User::create(['company_id' => $company->id, 'company_role' => 'owner', 'name' => 'C', 'email' => "$tin@t.uz", 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
    }

    private function upload(User $user, string $gtin): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "01{$gtin}21" . substr(bin2hex(random_bytes(10)), 0, 20) . "\x1D91ABCD\x1D92" . str_repeat('y', 44));
        $this->actingAs($user);
        app(ImportBatchService::class)->createFromUpload(new UploadedFile($path, 'p.csv', 'text/csv', null, true));
        $this->processBatches(); // the classifier fake answers "not found"
    }

    public function test_client_is_asked_to_name_an_unknown_item_and_can_do_it_once(): void
    {
        $client = $this->client('301000001');
        $this->upload($client, '04780000000110');
        $product = Product::withoutGlobalScope('owner')->where('gtin', '04780000000110')->firstOrFail();
        $this->assertTrue($product->needsName());

        $this->actingAs($client)->get(route('import-batches.index'))->assertOk()->assertSee('Nomini kiriting');

        Livewire::test(ImportBatchIndex::class)
            ->call('openNaming', $product->id)
            ->set('naming.name_uz', 'Choynak X1')
            ->call('saveNaming')->assertHasNoErrors();

        $this->assertSame('Choynak X1', $product->fresh()->name_uz);
        Livewire::test(ImportBatchIndex::class)->call('openNaming', $product->id)->assertForbidden(); // named → admin only now
    }

    public function test_other_company_cannot_name_it(): void
    {
        $this->upload($this->client('301000002'), '04780000000127');
        $product = Product::withoutGlobalScope('owner')->where('gtin', '04780000000127')->firstOrFail();

        $this->actingAs($this->client('301000003'));
        $this->assertFalse(auth()->user()->can('name', $product));
    }

    public function test_telegram_asks_for_the_name(): void
    {
        config(['telegram.token' => 'TEST']);
        $sent = [];
        \Illuminate\Support\Facades\Http::fake(function ($request) use (&$sent) {
            if (str_contains($request->url(), 'api.telegram.org')) {
                $sent[] = $request->data()['text'] ?? '';
                return \Illuminate\Support\Facades\Http::response(['ok' => true, 'result' => []]);
            }
            return \Illuminate\Support\Facades\Http::response(['success' => true, 'data' => []]);
        });
        $client = $this->client('301000004');
        $client->update(['telegram_chat_id' => '900']);

        $this->upload($client, '04780000000134');

        $this->assertNotEmpty(collect($sent)->filter(fn ($t) => str_contains($t, '04780000000134') && str_contains($t, 'nomini')));
    }

    public function test_batch_with_several_unknown_items_names_them_in_a_row_and_pdf_still_works(): void
    {
        $client = $this->client('301000005');
        $path = tempnam(sys_get_temp_dir(), 'csv');
        $lines = [];
        foreach (['04780000000141', '04780000000158', '04780000000165'] as $gtin) {
            $lines[] = "01{$gtin}21" . substr(bin2hex(random_bytes(10)), 0, 20) . "\x1D91ABCD\x1D92" . str_repeat('z', 44);
        }
        file_put_contents($path, implode("\n", $lines));
        $this->actingAs($client);
        $batch = app(ImportBatchService::class)->createFromUpload(new UploadedFile($path, 'mix.csv', 'text/csv', null, true));
        $this->processBatches();

        $this->get(route('import-batches.labels', $batch))->assertOk()->assertHeader('Content-Type', 'application/pdf'); // allowed without names

        $first = Product::withoutGlobalScope('owner')->where('gtin', '04780000000141')->first();
        $component = Livewire::test(ImportBatchIndex::class)->call('openNaming', $first->id, $batch->id)
            ->set('naming.name_uz', 'A')->call('saveNaming');
        $this->assertNotNull($component->get('namingId'), 'the next unnamed item opens');
        $component->set('naming.name_uz', 'B')->call('saveNaming')->set('naming.name_uz', 'C')->call('saveNaming');

        $this->assertNull($component->get('namingId'));
        $this->assertSame(0, Product::withoutGlobalScope('owner')->get()->filter->needsName()->count());
    }
}
