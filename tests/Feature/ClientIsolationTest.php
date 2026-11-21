<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Marking\Services\ImportBatchService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ClientIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'user'): User
    {
        static $n = 0;
        $n++;

        $company = $role === 'admin' ? null : \App\Domain\Companies\Company::create(['tin' => (string) (300000000 + $n), 'name' => "Company $n"]);

        return User::create(['company_id' => $company?->id, 'name' => "User $n", 'email' => "u$n@test.uz", 'password' => 'secret123', 'role' => $role, 'locale' => 'uz', 'is_active' => true]);
    }

    /** Uploads a CSV of `$count` fresh appliance codes for GTIN `$gtin` as `$user`. */
    private function import(User $user, string $gtin, int $count): ImportBatch
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $serial = substr(str_pad(bin2hex(random_bytes(10)), 20, '0'), 0, 20);
            $lines[] = "01{$gtin}21{$serial}\x1D91ABCD\x1D92" . substr(base64_encode(random_bytes(33)), 0, 44);
        }
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, implode("\n", $lines));

        $this->actingAs($user);
        $batch = app(ImportBatchService::class)->createFromUpload(new UploadedFile($path, 'codes.csv', 'text/csv', null, true));
        auth()->logout();
        $this->processBatches();

        return $batch->fresh();
    }

    public function test_registered_user_sees_only_own_data(): void
    {
        $ali = $this->user();
        $bek = $this->user();
        $admin = $this->user('admin');

        $aliBatch = $this->import($ali, '04780000000011', 30);
        $this->import($bek, '04780000000011', 20); // same GTIN, other client

        $this->assertSame(30, $aliBatch->processed_rows);

        $this->actingAs($ali);
        $this->assertSame(1, ImportBatch::count());
        $this->assertSame(30, MarkingCode::count());
        $this->assertSame(1, Product::count());
        $this->assertSame(30, Product::withCount('markingCodes')->first()->marking_codes_count, 'client counts only own codes');

        $this->actingAs($bek);
        $this->assertSame(20, MarkingCode::count());
        $this->assertNull(ImportBatch::find($aliBatch->id));

        $this->actingAs($admin);
        $this->assertSame(2, ImportBatch::count());
        $this->assertSame(50, MarkingCode::count());
        $this->assertSame(1, Product::count(), 'one product per GTIN, shared by both owners');
        $owners = Product::ownersOf([Product::first()->id])[Product::first()->id];
        $this->assertSame([[$ali->id, 30], [$bek->id, 20]], array_map(fn ($o) => [$o['user']->id, $o['codes']], $owners));

        $carol = $this->user();
        $this->actingAs($carol);
        $this->assertSame(0, Product::count(), 'a client without codes for the GTIN does not see the product');
    }

    public function test_registered_user_cannot_open_other_clients_pdf_or_admin_pages(): void
    {
        $ali = $this->user();
        $bek = $this->user();
        $batch = $this->import($ali, '04780000000028', 3);

        $this->actingAs($bek)->get(route('import-batches.labels', $batch))->assertNotFound();
        $this->actingAs($bek)->get('/admin/templates')->assertForbidden();
        $this->actingAs($bek)->get('/admin/companies')->assertForbidden();
        $this->actingAs($bek)->get(route('import-batches.index'))->assertOk();
    }

    public function test_super_admin_is_sent_from_client_pages_to_admin_pages(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('import-batches.index'))->assertRedirect(route('admin.clients'));
        $this->actingAs($admin)->get(route('templates.catalog'))->assertRedirect(route('label-templates.index'));
        $this->actingAs($admin)->get(route('products.index'))->assertOk();
    }

    public function test_client_cannot_edit_shared_products(): void
    {
        $ali = $this->user();
        $this->import($ali, '04780000000059', 2);
        $product = Product::withoutGlobalScope('owner')->firstOrFail();

        $this->actingAs($ali);
        \Livewire\Livewire::test(\App\Http\Livewire\Catalog\ProductIndex::class)->call('openEdit', $product->id)->set('name_uz', 'X')->call('save')->assertForbidden();
        $this->assertNull($product->fresh()->name_uz);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/aslbelgi')->assertRedirect('/login');
        $this->get('/admin/companies')->assertRedirect('/login');
    }

    public function test_registration_creates_a_registered_user(): void
    {
        $this->post('/register', [
            'name' => 'Ali', 'company' => 'Texno', 'email' => 'Ali@Test.uz', 'phone' => '+998901112233',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('import-batches.index'));

        $user = User::where('email', 'ali@test.uz')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_super_admin_sees_clients_with_usage(): void
    {
        $ali = $this->user();
        $this->import($ali, '04780000000035', 12);

        $this->actingAs($this->user('admin'))
            ->get('/admin/companies')
            ->assertOk()
            ->assertSee($ali->company->name)
            ->assertSee('12');
        $this->actingAs($this->user('admin'))->get(route('admin.companies.show', $ali->company))->assertOk()->assertSee($ali->email);
    }

    public function test_client_previews_templates_but_cannot_design(): void
    {
        $this->seed(\Database\Seeders\OfferSeeder::class);
        $client = $this->user();
        $template = \App\Domain\Printing\Models\LabelTemplate::default();

        $this->actingAs($client)->get(route('templates.catalog'))->assertOk()->assertSee($template->name);
        $this->actingAs($client)->get(route('templates.preview', $template))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($client)->get(route('label-templates.index'))->assertForbidden();
        $this->actingAs($client)->get(route('import-batches.index'))->assertOk()->assertDontSee(route('studio'));
    }

    public function test_dev_role_switch_is_unavailable_outside_local(): void
    {
        $this->get('/dev/as/admin')->assertNotFound();
        $this->get('/dev/as/client')->assertNotFound();
    }
}
