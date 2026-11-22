<?php

namespace Tests\Feature;

use App\Domain\Companies\Company;
use App\Domain\Printing\Models\LabelTemplate;
use App\Http\Livewire\Printing\TemplateCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private function template(string $name, int $w, int $h, bool $default = false): LabelTemplate
    {
        return LabelTemplate::create(['name' => $name, 'printer_type' => 'pdf_browser', 'width_mm' => $w, 'height_mm' => $h, 'dpi' => 203,
            'template' => '{datamatrix}', 'layout' => LabelTemplate::DEFAULT_LAYOUT, 'is_default' => $default]);
    }

    public function test_size_filter_and_my_templates_drive_the_pdf_picker(): void
    {
        $a = $this->template('Default 20×20', 20, 20, true);
        $b = $this->template('Karobka 58×40', 58, 40);
        $c = $this->template('Kichik 25×25', 25, 25);
        $company = Company::create(['tin' => '307797292', 'name' => 'Test', 'is_active' => true]);
        $user = User::create(['name' => 'O', 'email' => 'o@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true,
            'company_id' => $company->id, 'company_role' => 'member']);
        $this->actingAs($user);

        // nothing chosen yet: every template is offered
        $this->assertSame(LabelTemplate::count(), LabelTemplate::forCompany($company)->count());

        Livewire::test(TemplateCatalog::class)
            ->set('width', 60)->set('height', 38)->assertSee('Karobka 58×40')->assertDontSee('Kichik 25×25')   // ±5 mm
            ->set('width', 64)->assertDontSee('Karobka 58×40')                                                 // 6 mm away
            ->set('width', '')->set('height', 22)->assertSee('Default 20×20')->assertSee('Kichik 25×25')->assertDontSee('Karobka 58×40')
            ->call('clearSize')->call('toggleMine', $b->id)->call('toggleMine', $c->id)
            ->set('mine', true)->assertSee('Karobka 58×40')->assertDontSee('Default 20×20')
            ->call('toggleMine', $c->id)->assertDontSee('Kichik 25×25');

        // chosen: only "my templates" are offered for PDF / Telegram
        $this->assertSame([$b->id], LabelTemplate::forCompany($company->fresh())->pluck('id')->all());
        $this->get(route('templates.catalog', ['w' => 58, 'h' => 40]))->assertOk()->assertSee('Karobka 58×40');
    }

    public function test_finished_template_request_lands_in_my_templates(): void
    {
        $t = $this->template('Buyurtma 30×20', 30, 20);
        $company = Company::create(['tin' => '307797293', 'name' => 'Test 2', 'is_active' => true]);
        $request = \App\Domain\Printing\Models\TemplateRequest::create(['company_id' => $company->id, 'width_mm' => 30, 'height_mm' => 20, 'columns' => 1]);

        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]));
        Livewire::test(\App\Http\Livewire\Admin\TemplateRequestIndex::class)
            ->call('open', $request->id)->set('templateId', $t->id)->call('answer', 'done');

        $this->assertSame([$t->id], $company->labelTemplates()->pluck('label_templates.id')->all());
    }
}
