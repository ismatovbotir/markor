<?php

namespace Tests\Feature;

use App\Domain\Printing\Models\LabelMark;
use App\Domain\Printing\Renderers\LayoutRenderer;
use App\Http\Livewire\Admin\LabelMarkForm;
use App\Http\Livewire\Admin\LabelMarkIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Label icons ("Знаки" menu): built-in Asl Belgisi + public-domain marks, managed by the super admin
 * (add, replace the image, hide — never delete).
 */
class LabelMarkTest extends TestCase
{
    use RefreshDatabase;

    private function design(array $mark): array
    {
        return ['width_mm' => 40, 'height_mm' => 30, 'copies' => 1, 'watermark' => false,
            'elements' => [['type' => 'mark', 'x' => 1, 'y' => 1, 'w' => 8, 'h' => 8] + $mark]];
    }

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
    }

    public function test_every_built_in_mark_has_an_image_three_names_and_prints(): void
    {
        $this->assertContains('aslbelgi', LabelMark::keys());
        foreach (LabelMark::where('key', '!=', 'chestny_znak')->get() as $m) {
            $this->assertFileExists(public_path($m->path), $m->key);
            $this->assertNotEmpty($m->name_ru, $m->key);
            $this->assertNotEmpty($m->name_zh, $m->key);
            $this->assertNotEmpty($m->source, $m->key);
            $image = LayoutRenderer::mark($m->key);
            $this->assertStringStartsWith('data:image/png;base64,', $image['uri']);
            $label = ['width_mm' => 30, 'height_mm' => 20, 'elements' => [['type' => 'mark', 'mark' => $m->key, 'x' => 1, 'y' => 1, 'w' => 8, 'h' => 8]]];
            $this->assertStringContainsString($image['uri'], app(LayoutRenderer::class)->render($label, [], 0, now()), $m->key);
        }
        $html = $this->postJson(route('studio.print'), $this->design(['mark' => 'eac']))->assertOk()->getContent();
        $this->assertStringContainsString(LayoutRenderer::mark('eac')['uri'], $html);

        $this->postJson(route('studio.print'), $this->design(['mark' => 'other']))->assertStatus(422);
        $this->postJson(route('studio.print'), $this->design([]))->assertStatus(422);
        $this->assertNull(LayoutRenderer::mark('../../.env'));
    }

    public function test_chestny_znak_waits_for_the_official_file(): void
    {
        $cz = LabelMark::where('key', 'chestny_znak')->first();
        $this->assertNull($cz->path);
        $this->assertStringContainsString('ЦРПТ', $cz->source);
        $this->assertNotContains('chestny_znak', LabelMark::keys());
        $this->assertArrayNotHasKey('chestny_znak', collect(LabelMark::menu())->flatten(1)->keyBy('key')->all());
    }

    public function test_designers_show_the_grouped_menu_with_asl_belgisi_inside(): void
    {
        foreach (['views/livewire/printing/partials/template-editor.blade.php', 'views/studio/index.blade.php'] as $view) {
            $src = file_get_contents(resource_path($view));
            $this->assertStringContainsString("@include('partials.label-marks-menu'", $src);
            $this->assertStringNotContainsString("add('mark', 'aslbelgi')", $src); // moved into the menu
        }

        $menu = $this->blade("@include('partials.label-marks-menu', ['chip' => ''])");
        foreach (LabelMark::menu() as $group => $marks) {
            $menu->assertSee(__("labels.marks_group.$group"));
            foreach ($marks as $m) {
                $menu->assertSee("add('mark', '{$m['key']}')", false);
            }
        }
        $menu->assertSee(__('labels.marks_conformity_hint'));
        $this->assertSame('marking', array_key_first(LabelMark::menu()));
    }

    public function test_admin_adds_a_mark_and_it_prints_and_appears_in_the_menu(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        Livewire::test(LabelMarkForm::class)
            ->set('item.key', 'halal')->set('item.group', 'info')->set('item.name_uz', 'Halol')
            ->set('photo', UploadedFile::fake()->image('halal.png', 300, 300))
            ->call('save')->assertHasNoErrors();

        $m = LabelMark::where('key', 'halal')->firstOrFail();
        $this->assertStringStartsWith('storage/label-marks/', $m->path);
        Storage::disk('public')->assertExists(substr($m->path, 8));
        $this->assertSame(300, $m->width);
        $this->assertContains('halal', collect(LabelMark::menu()['info'])->pluck('key')->all());
        $this->assertNotNull(LayoutRenderer::mark('halal'));
        $this->postJson(route('studio.print'), $this->design(['mark' => 'halal']))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'label_mark.created', 'entity_id' => $m->id]);

        // key rules: required image on create, unique lowercase key
        Livewire::test(LabelMarkForm::class)->set('item.key', 'halal')->set('item.name_uz', 'X')->call('save')
            ->assertHasErrors(['item.key', 'photo']);
        Livewire::test(LabelMarkForm::class)->set('item.key', 'Bad Key')->set('item.name_uz', 'X')
            ->set('photo', UploadedFile::fake()->image('x.png', 100, 100))->call('save')->assertHasErrors(['item.key']);
    }

    public function test_admin_replaces_an_image_keeps_the_old_file_and_cannot_change_the_key(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $eac = LabelMark::where('key', 'eac')->first();
        $before = LayoutRenderer::mark('eac')['uri'];

        Livewire::test(LabelMarkForm::class, ['mark' => $eac])
            ->set('item.key', 'changed')
            ->set('photo', UploadedFile::fake()->image('eac2.png', 200, 200))
            ->call('save')->assertHasNoErrors();

        $eac->refresh();
        $this->assertSame('eac', $eac->key);
        $this->assertStringStartsWith('storage/label-marks/', $eac->path);
        $this->assertFileExists(public_path('images/marks/eac.png')); // the old file stays
        $this->assertNotSame($before, LayoutRenderer::mark('eac')['uri']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'label_mark.image_replaced', 'entity_id' => $eac->id]);

        // the Честный ЗНАК slot gets its official file
        $cz = LabelMark::where('key', 'chestny_znak')->first();
        Livewire::test(LabelMarkForm::class, ['mark' => $cz])->set('photo', UploadedFile::fake()->image('cz.png', 400, 120))->call('save')->assertHasNoErrors();
        $this->assertContains('chestny_znak', collect(LabelMark::menu()['marking'])->pluck('key')->all());
    }

    public function test_hidden_mark_leaves_the_menu_but_old_templates_still_print_it_and_nothing_deletes(): void
    {
        $this->actingAs($this->admin());
        $fragile = LabelMark::where('key', 'fragile')->first();

        Livewire::test(LabelMarkIndex::class)->call('toggle', $fragile->id);
        $this->assertFalse($fragile->fresh()->is_active);
        $this->assertNotContains('fragile', collect(LabelMark::menu()['handling'])->pluck('key')->all());
        $this->postJson(route('studio.print'), $this->design(['mark' => 'fragile']))->assertOk();
        $this->assertArrayHasKey('fragile', LabelMark::urls());

        $this->assertFalse(method_exists(LabelMarkIndex::class, 'delete'));
        $this->assertFalse(method_exists(LabelMarkForm::class, 'delete'));
        $this->get(route('admin.label-marks'))->assertOk()->assertSee(__('label_marks.no_delete'));
        $this->get(route('admin.label-marks.edit', $fragile))->assertOk();
    }

    public function test_clients_cannot_open_the_icon_admin(): void
    {
        $client = User::create(['name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
        $this->actingAs($client)->get(route('admin.label-marks'))->assertForbidden();
    }
}
