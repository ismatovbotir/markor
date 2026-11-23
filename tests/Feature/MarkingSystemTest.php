<?php

namespace Tests\Feature;

use App\Domain\Aslbelgi\AslbelgiCheckService;
use App\Domain\Catalog\Models\ProductGroup;
use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Marking\Parsers\MarkingCodeParser;
use App\Domain\Marking\Services\ImportBatchService;
use App\Jobs\ProcessImportBatchJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Asl Belgisi and Честный ЗНАК codes: the system is detected from the file, or chosen when ambiguous. */
class MarkingSystemTest extends TestCase
{
    use RefreshDatabase;

    // 83 chars: (01)(21)13 (91)4 (92)44 — the same structure in Uzbekistan and Russia
    private const LONG_83 = '0104601234567893' . '21ABCDEFGHIJ123' . '91EE06' . '92' . 'abcdefghijABCDEFGHIJabcdefghijABCDEFGHIJ1234';
    // 37 chars: (01)(21)13 (93)4 — Russia only
    private const RU_SHORT = '0104601234567893' . '21ZYXWVUTSRQ987' . '93AbC1';
    // 90 chars: Uzbek household appliances (serial 20) — Asl Belgisi only
    private const UZ_90 = '010432345678817621AF9-&eDmMbl?6CkOyAEB91UZF092TDRFasF7Lf27Qv4/muaq3qoqmZ5x3WAKhKoLmzlyNi4=';

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ProductGroupSeeder::class);
        $this->company = Company::create(['tin' => '307797295', 'name' => 'Exporter', 'is_active' => true]);
        $this->user = User::create(['name' => 'O', 'email' => 'o@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true,
            'company_id' => $this->company->id, 'company_role' => 'owner']);
    }

    private function import(array $codes): ImportBatch
    {
        $name = uniqid() . '.csv';
        @mkdir(storage_path('app/imports'), 0777, true);
        file_put_contents(storage_path("app/imports/{$name}"), implode("\n", $codes) . "\n");
        $batch = ImportBatch::create(['uuid' => uniqid(), 'filename' => $name, 'original_filename' => $name, 'uploaded_by' => $this->user->id,
            'company_id' => $this->company->id, 'status' => 'pending']);
        app()->call([new ProcessImportBatchJob($batch), 'handle']);

        return $batch->fresh();
    }

    private function systemOfCode(string $code): string
    {
        return ProductGroup::find(MarkingCode::withoutGlobalScope('owner')->where('code', $code)->value('product_group_id'))->marking_system;
    }

    public function test_parser_tells_the_system_when_a_structure_is_unique(): void
    {
        $parser = new MarkingCodeParser();
        $this->assertSame(['chestny_znak'], $parser->systemsOf(self::RU_SHORT));
        $this->assertSame(['aslbelgisi'], $parser->systemsOf(self::UZ_90));
        $this->assertEqualsCanonicalizing(['aslbelgisi', 'chestny_znak'], $parser->systemsOf(self::LONG_83));

        $this->assertSame(['chestny_znak', true], $parser->detectSystem([self::LONG_83, self::RU_SHORT]));
        $this->assertSame(['chestny_znak', false], $parser->detectSystem([self::LONG_83], 'chestny_znak'));
        $this->assertSame([null, false], $parser->detectSystem(['not a code']));
    }

    public function test_gtin_prefix_breaks_the_tie_for_shared_structures(): void
    {
        $parser = new MarkingCodeParser();
        $ru83 = '0104690690129455' . '21LI.9c9:KOXK8n' . "\x1D" . '91EE06' . "\x1D" . '92' . 'qF4BUQ/aEnG7ZyBvXwL+nHsFBBxj+7OnNPnJzC6JtZc='; // shape of a real RU clothing code
        $uz83 = '0104780000000059' . '21ABCDEFGHIJ123' . '91EE06' . '92' . 'abcdefghijABCDEFGHIJabcdefghijABCDEFGHIJ1234';
        $other83 = '0108712345678906' . '21ABCDEFGHIJ123' . '91EE06' . '92' . 'abcdefghijABCDEFGHIJabcdefghijABCDEFGHIJ1234'; // 871: neither

        $this->assertSame('chestny_znak', MarkingCodeParser::gtinHint($parser->normalize($ru83)));
        $this->assertSame(['chestny_znak', false], $parser->detectSystem([$ru83], 'aslbelgisi'));   // prefix beats the company default
        $this->assertSame(['aslbelgisi', false], $parser->detectSystem([$uz83], 'chestny_znak'));
        $this->assertSame(['chestny_znak', false], $parser->detectSystem([$other83], 'chestny_znak')); // no hint: the company default
        $this->assertSame(['aslbelgisi', true], $parser->detectSystem([$ru83, self::UZ_90]));       // a unique structure still wins
    }

    public function test_one_russian_only_code_files_the_whole_batch_under_chestny_znak(): void
    {
        $batch = $this->import([self::LONG_83, self::RU_SHORT]);

        $this->assertSame('chestny_znak', $batch->marking_system);
        $this->assertTrue($batch->marking_system_detected);
        $this->assertSame('chestny_znak', $this->systemOfCode(self::LONG_83)); // the ambiguous code follows the file
        $this->assertSame('chestny_znak', $this->company->fresh()->marking_system); // learned
        $this->assertSame('aslbelgi_check.other_system', app(AslbelgiCheckService::class)->blocker($batch, $this->user));
    }

    public function test_ambiguous_file_uses_the_usual_market_and_the_client_may_switch(): void
    {
        $this->company->update(['marking_system' => 'chestny_znak']);
        $batch = $this->import([self::LONG_83]);

        $this->assertSame('chestny_znak', $batch->marking_system);
        $this->assertFalse($batch->marking_system_detected);

        app(ImportBatchService::class)->switchSystem($batch, 'aslbelgisi');
        $this->assertSame('aslbelgisi', $batch->fresh()->marking_system);
        $this->assertSame('aslbelgisi', $this->systemOfCode(self::LONG_83));
        $this->assertSame('aslbelgisi', $this->company->fresh()->marking_system);
    }

    public function test_detected_batch_cannot_be_switched(): void
    {
        $batch = $this->import([self::UZ_90]);
        $this->assertSame('aslbelgisi', $batch->marking_system);

        $this->expectExceptionMessage('marking_system.fixed');
        app(ImportBatchService::class)->switchSystem($batch, 'chestny_znak');
    }
}
