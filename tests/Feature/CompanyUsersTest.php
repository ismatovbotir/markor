<?php

namespace Tests\Feature;

use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Marking\Services\ImportBatchService;
use App\Http\Livewire\Account\CompanyUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyUsersTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $tin = '301111111'): User
    {
        $company = Company::create(['tin' => $tin, 'name' => "C $tin"]);

        return User::create(['company_id' => $company->id, 'company_role' => 'owner', 'name' => 'Owner', 'email' => "o$tin@t.uz", 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'phone' => '+998901112233', 'is_active' => true]);
    }

    private function upload(User $user, int $count): void
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = '0104780000000103' . '21' . substr(bin2hex(random_bytes(10)), 0, 20) . "\x1D91ABCD\x1D92" . substr(base64_encode(random_bytes(33)), 0, 44);
        }
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, implode("\n", $lines));
        $this->actingAs($user);
        app(ImportBatchService::class)->createFromUpload(new UploadedFile($path, 'c.csv', 'text/csv', null, true));
        $this->processBatches();
    }

    public function test_owner_adds_a_member_who_shares_company_data(): void
    {
        $owner = $this->owner();
        $this->upload($owner, 4);

        $this->actingAs($owner);
        Livewire::test(CompanyUsers::class)
            ->call('openCreate')
            ->set('form.name', 'Omborchi')->set('form.email', 'ombor@t.uz')->set('form.phone', '+998 93 000 11 22')->set('form.password', 'secret123')
            ->call('save')->assertHasNoErrors();

        $member = User::where('email', 'ombor@t.uz')->firstOrFail();
        $this->assertSame($owner->company_id, $member->company_id);
        $this->assertSame('member', $member->company_role);

        $this->upload($member, 3);
        $this->actingAs($member);
        $this->assertSame(2, ImportBatch::count(), 'member sees the owner\'s batch too');
        $this->assertSame(7, MarkingCode::count());

        $this->actingAs($owner);
        $this->assertSame(7, MarkingCode::count());
    }

    public function test_other_company_sees_nothing_and_member_cannot_manage_users(): void
    {
        $owner = $this->owner();
        $this->upload($owner, 2);
        $member = User::create(['company_id' => $owner->company_id, 'company_role' => 'member', 'name' => 'M', 'email' => 'm@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
        $stranger = $this->owner('302222222');

        $this->actingAs($stranger);
        $this->assertSame(0, ImportBatch::count());
        $this->assertSame(0, MarkingCode::count());

        $this->actingAs($member);
        Livewire::test(CompanyUsers::class)->call('openCreate')->assertForbidden();
        $this->get(route('company.users'))->assertOk()->assertSee('Owner');
    }

    public function test_removed_member_loses_access_but_data_stays(): void
    {
        $owner = $this->owner();
        $member = User::create(['company_id' => $owner->company_id, 'company_role' => 'member', 'name' => 'M', 'email' => 'm@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
        $this->upload($member, 2);

        $this->actingAs($owner);
        Livewire::test(CompanyUsers::class)->call('remove', $member->id);

        $this->assertNull($member->fresh()->company_id);
        $this->assertSame(2, MarkingCode::count(), 'codes stay with the company');
    }

    public function test_super_admin_blocks_a_company(): void
    {
        $owner = $this->owner('305555555');
        $admin = User::create(['name' => 'A', 'email' => 'adm@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);

        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Http\Livewire\Admin\CompanyShow::class, ['company' => $owner->company])
            ->set('blockReason', "To'lov kutilmoqda")->call('toggleCompany');
        $this->assertFalse($owner->company->fresh()->is_active);

        $this->actingAs($owner->fresh())->get(route('import-batches.index'))->assertForbidden()->assertSee("To'lov kutilmoqda");

        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Http\Livewire\Admin\CompanyShow::class, ['company' => $owner->company->fresh()])->call('toggleCompany');
        $this->actingAs($owner->fresh())->get(route('import-batches.index'))->assertOk();
    }
}
