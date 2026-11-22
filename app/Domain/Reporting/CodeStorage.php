<?php

namespace App\Domain\Reporting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Kodlar ombori" for the super admin: how much each company keeps — counts and sizes only, never code values
 * (CLAUDE.md §15.7). Sizes are estimates: the codes table size shared out by each company's row count.
 */
class CodeStorage
{
    /** status groups shown in the table */
    public const GROUPS = ['imported' => ['imported', 'assigned'], 'printed' => ['printed', 'aggregated', 'shipped'], 'cancelled' => ['cancelled']];

    /** @return array<int, array> per company_id */
    public function perCompany(): array
    {
        $codes = DB::table('marking_codes')->whereNull('deleted_at')->whereNotNull('company_id')
            ->selectRaw('company_id, count(*) as total, max(printed_at) as last_print')
            ->selectRaw("sum(case when status in ('imported','assigned') then 1 else 0 end) as imported")
            ->selectRaw("sum(case when status in ('printed','aggregated','shipped') then 1 else 0 end) as printed")
            ->selectRaw("sum(case when status = 'cancelled' then 1 else 0 end) as cancelled")
            ->when(Schema::hasColumn('marking_codes', 'aslbelgi_problem'), fn ($q) => $q->selectRaw('sum(case when aslbelgi_problem is not null then 1 else 0 end) as problems'))
            ->groupBy('company_id')->get()->keyBy('company_id');

        $batches = DB::table('import_batches')->whereNotNull('company_id')
            ->selectRaw('company_id, count(*) as batches, min(created_at) as first_batch, max(created_at) as last_upload')
            ->groupBy('company_id')->get()->keyBy('company_id');

        $pdfs = DB::table('label_pdfs')->whereNotNull('company_id')
            ->selectRaw('company_id, sum(case when local_path is not null then size_bytes else 0 end) as cached_bytes, sum(case when local_path is not null then 1 else 0 end) as cached')
            ->selectRaw('sum(case when telegram_file_id is not null then 1 else 0 end) as archived')
            ->groupBy('company_id')->get()->keyBy('company_id');

        $perRow = $this->bytesPerCode();
        $out = [];
        foreach ($codes->keys()->merge($batches->keys())->merge($pdfs->keys())->unique() as $id) {
            $c = $codes[$id] ?? null;
            $out[$id] = [
                'total' => (int) ($c->total ?? 0), 'imported' => (int) ($c->imported ?? 0), 'printed' => (int) ($c->printed ?? 0),
                'cancelled' => (int) ($c->cancelled ?? 0), 'problems' => (int) ($c->problems ?? 0),
                'batches' => (int) ($batches[$id]->batches ?? 0), 'first_batch' => $batches[$id]->first_batch ?? null,
                'last_upload' => $batches[$id]->last_upload ?? null, 'last_print' => $c->last_print ?? null,
                'db_bytes' => (int) round(($c->total ?? 0) * $perRow),
                'pdf_bytes' => (int) ($pdfs[$id]->cached_bytes ?? 0), 'pdf_cached' => (int) ($pdfs[$id]->cached ?? 0), 'pdf_archived' => (int) ($pdfs[$id]->archived ?? 0),
            ];
        }

        return $out;
    }

    public function summary(): array
    {
        return [
            'db_bytes'    => $this->databaseBytes(),
            'codes_bytes' => $this->tableBytes('marking_codes') ?: (int) round(DB::table('marking_codes')->count() * $this->bytesPerCode()), // estimate where the DB cannot tell
            'codes'       => DB::table('marking_codes')->whereNull('deleted_at')->count(),
            'codes_month' => DB::table('marking_codes')->whereNull('deleted_at')->where('created_at', '>=', now()->startOfMonth())->count(),
            'pdf_bytes'   => (int) DB::table('label_pdfs')->whereNotNull('local_path')->sum('size_bytes'),
            'companies'   => DB::table('marking_codes')->whereNull('deleted_at')->distinct()->count('company_id'),
        ];
    }

    /** Average stored bytes per code row (table + indexes), or a fair guess when the database cannot say. */
    public function bytesPerCode(): float
    {
        $rows = DB::table('marking_codes')->count();
        $bytes = $this->tableBytes('marking_codes');

        return $rows > 0 && $bytes > 0 ? $bytes / $rows : 400.0;
    }

    private function tableBytes(string $table): int
    {
        try {
            return match (DB::connection()->getDriverName()) {
                'pgsql'          => (int) DB::selectOne('select pg_total_relation_size(?) as b', [$table])->b,
                'mysql', 'mariadb' => (int) DB::selectOne('select data_length + index_length as b from information_schema.tables where table_schema = database() and table_name = ?', [$table])->b,
                'sqlite'         => (int) (DB::selectOne("select sum(pgsize) as b from dbstat where name = ? or name in (select name from sqlite_master where tbl_name = ? and type = 'index')", [$table, $table])->b ?? 0),
                default          => 0,
            };
        } catch (\Throwable) {
            return 0; // e.g. SQLite built without dbstat
        }
    }

    private function databaseBytes(): int
    {
        try {
            return match (DB::connection()->getDriverName()) {
                'pgsql'          => (int) DB::selectOne('select pg_database_size(current_database()) as b')->b,
                'mysql', 'mariadb' => (int) DB::selectOne('select sum(data_length + index_length) as b from information_schema.tables where table_schema = database()')->b,
                'sqlite'         => is_file($f = (string) DB::connection()->getDatabaseName()) ? (int) filesize($f) : 0,
                default          => 0,
            };
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function bytes(int|float $b): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $i => $unit) {
            if ($b < 1024 || $unit === 'TB') {
                return ($i === 0 ? (int) $b : number_format($b, $b < 10 ? 1 : 0, '.', ' ')) . ' ' . $unit;
            }
            $b /= 1024;
        }

        return (string) $b;
    }
}
