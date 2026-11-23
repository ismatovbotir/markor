<?php

namespace Tests\Feature;

use App\Domain\Companies\Company;
use App\Domain\Printing\Models\LabelTemplate;
use App\Http\Livewire\Printing\LabelTemplateIndex;
use App\Http\Livewire\Printing\MyTemplates;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Client-made templates: private to the company; global only by the super admin with the client's consent. */
class ClientTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $tin): User
    {
        $company = Company::create(['tin' => $tin, 'name' => 'Co ' . $tin, 'is_active' => true]);

        return User::create(['name' => $tin, 'email' => "$tin@t.uz", 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true,
            'company_id' => $company->id, 'company_role' => 'owner']);
    }

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
    }

    private function payload(string $name): array
    {
        return ['name' => $name, 'width_mm' => 30, 'height_mm' => 20, 'columns' => 1, 'gap_mm' => 0, 'labels_per_pdf' => 2000,
            'elements' => [['type' => 'datamatrix', 'x' => 2, 'y' => 2, 'w' => 16, 'h' => 16]]];
    }

    public function test_client_template_is_private_and_publishing_needs_consent(): void
    {
        $ali = $this->client('307797301');
        $vali = $this->client('307797302');

        $this->actingAs($ali);
        Livewire::test(MyTemplates::class)->call('openCopy')->call('save', $this->payload('Ali 30x20'));
        $t = LabelTemplate::where('name', 'Ali 30x20')->firstOrFail();
        $this->assertSame($ali->company_id, $t->company_id);
        $this->assertFalse($t->share_consent);

        // another client never sees it (lists, PDF preview, editing)
        $this->actingAs($vali);
        $this->assertNull(LabelTemplate::find($t->id));
        $this->get(route('templates.preview', $t))->assertNotFound();
        $this->assertNull(LabelTemplate::forCompany($vali->company)->find($t->id));
        $this->assertNull(LabelTemplate::public()->find($t->id)); // guest pages, marketplace

        // the super admin sees it with the owner, can't edit it, can't publish it without consent
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->assertFalse($admin->can('update', $t));
        Livewire::test(LabelTemplateIndex::class)->set('owner', 'clients')->assertSee('Ali 30x20')->assertSee('Co 307797301')
            ->call('share', $t->id, true)->assertForbidden();
        $this->assertNull($t->fresh()->shared_at);

        // the client agrees → the super admin publishes → everyone sees it
        $this->actingAs($ali);
        Livewire::test(MyTemplates::class)->call('openEdit', $t->id)->set('shareConsent', true)->call('save', $this->payload('Ali 30x20'));
        $this->assertTrue($t->fresh()->share_consent);
        $this->actingAs($admin);
        Livewire::test(LabelTemplateIndex::class)->call('share', $t->id, true);
        $this->assertNotNull($t->fresh()->shared_at);
        $this->actingAs($vali);
        $this->assertNotNull(LabelTemplate::find($t->id));

        // withdrawing consent closes it again
        $this->actingAs($ali);
        Livewire::test(MyTemplates::class)->call('openEdit', $t->id)->set('shareConsent', false)->call('save', $this->payload('Ali 30x20'));
        $this->assertNull($t->fresh()->shared_at);
        $this->actingAs($vali);
        $this->assertNull(LabelTemplate::find($t->id));
    }

    public function test_deleted_design_goes_to_the_admin_and_the_limit_is_per_company(): void
    {
        $ali = $this->client('307797304');
        $ali->company->update(['template_limit' => 2]);
        $this->actingAs($ali);
        // a new design starts with the grey markor.uz mark switched on
        Livewire::test(MyTemplates::class)->call('openCopy')->assertSet('editor.watermark', true);
        foreach (['One', 'Two'] as $name) {
            Livewire::test(MyTemplates::class)->call('openCopy')->call('save', $this->payload($name));
        }
        // limit reached: no third one
        Livewire::test(MyTemplates::class)->call('openCopy')->assertSet('showForm', false);
        $this->assertSame(2, LabelTemplate::ownedBy($ali->company_id)->count());


        // deleting frees a slot and hides it from the client; consent was given for "One"
        $one = LabelTemplate::where('name', 'One')->first();
        $one->update(['share_consent' => true]);
        $two = LabelTemplate::where('name', 'Two')->first();
        Livewire::test(\App\Http\Livewire\Printing\TemplateCatalog::class)->call('deleteOwn', $one->id)->call('deleteOwn', $two->id);
        $this->assertNull(LabelTemplate::find($one->id));
        $this->assertSame(2, \App\Domain\Printing\Services\LabelTemplateService::slotsLeft($ali->company->fresh()));

        // the super admin sees both as "deleted by the client"; only the consented one can become global
        $this->actingAs($this->admin());
        Livewire::test(LabelTemplateIndex::class)->set('owner', 'deleted')->assertSee('One')->assertSee('Two')
            ->call('share', $one->id, true)
            ->call('share', $two->id, true)->assertForbidden();
        $this->assertNotNull($one->fresh()->shared_at);
        $this->assertNull($two->fresh()->shared_at);
        $this->actingAs($this->client('307797305'));
        $this->assertNotNull(LabelTemplate::find($one->id)); // everyone sees the accepted one
    }

    public function test_client_cannot_edit_global_or_other_templates(): void
    {
        $global = LabelTemplate::create(['name' => 'Global', 'printer_type' => 'pdf_browser', 'width_mm' => 20, 'height_mm' => 20, 'dpi' => 203,
            'template' => '{datamatrix}', 'layout' => LabelTemplate::DEFAULT_LAYOUT]);
        $ali = $this->client('307797303');
        $this->actingAs($ali);

        $this->assertFalse($ali->can('update', $global));
        Livewire::test(MyTemplates::class)->call('openEdit', $global->id)->assertForbidden();
        // copying a global template is fine: the copy is the client's own
        Livewire::test(MyTemplates::class)->call('openCopy', $global->id)->call('save', $this->payload('Copy'));
        $this->assertSame($ali->company_id, LabelTemplate::where('name', 'Copy')->value('company_id'));
    }
}
