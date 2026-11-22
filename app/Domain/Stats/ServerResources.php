<?php

namespace App\Domain\Stats;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Server and database figures for Admin → Statistika (heavy ones cached for 10 minutes).
 * Every figure is read defensively: hosting often restricts /proc (open_basedir — even is_readable() warns),
 * disk functions, sys_getloadavg or information_schema. A figure that cannot be read is null ("—"), never a 500.
 */
class ServerResources
{
    public static function snapshot(): array
    {
        $base = base_path();
        $total = self::safe(fn () => @disk_total_space($base) ?: 0, 0);
        $free = self::safe(fn () => @disk_free_space($base) ?: 0, 0);

        return [
            'disk_total' => $total,
            'disk_free'  => $free,
            'disk_used_percent' => $total ? round(($total - $free) / $total * 100, 1) : null,
            'folders'    => self::shape(self::safe(fn () => Cache::remember('stats.folders', 600, fn () => [
                'storage/app'  => self::dirSize(storage_path('app')),
                'storage/logs' => self::dirSize(storage_path('logs')),
                'public'       => self::dirSize(public_path()),
            ]), []), fn ($v) => is_array($v), []),
            'database'   => self::shape(self::safe(fn () => Cache::remember('stats.database', 600, fn () => self::database())),
                fn ($v) => is_array($v) && array_key_exists('size', $v) && is_array($v['tables'] ?? null), ['size' => null, 'tables' => []]),
            'queue'      => [
                'jobs'   => self::count('jobs'),
                'failed' => self::count('failed_jobs'),
                'oldest' => self::oldestJob(),
            ],
            'load'       => self::safe(fn () => function_exists('sys_getloadavg') ? (@sys_getloadavg() ?: null) : null),
            'cpus'       => self::safe(fn () => self::cpus()),
            'memory'     => self::safe(fn () => self::memory()),
            'php'        => PHP_VERSION,
            'laravel'    => app()->version(),
            'db_driver'  => DB::connection()->getDriverName(),
        ];
    }

    /** A cached value of the wrong shape (old format, null) falls back to $default instead of breaking the view. */
    private static function shape(mixed $value, \Closure $valid, mixed $default): mixed
    {
        return $valid($value) ? $value : $default;
    }

    /** Run one probe; any error or PHP warning (turned into an exception by Laravel) gives $default. */
    private static function safe(\Closure $probe, mixed $default = null): mixed
    {
        try {
            return $probe();
        } catch (\Throwable $e) {
            report($e); // visible in the log, but the page still opens
            return $default;
        }
    }

    private static function count(string $table): ?int
    {
        try {
            return DB::table($table)->count();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function oldestJob(): ?int
    {
        try {
            $at = DB::table('jobs')->min('created_at');

            return $at ? max(0, time() - (int) $at) : null; // seconds waiting
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{size: int|null, tables: array<int, array{name: string, rows: int|null, size: int|null}>} */
    private static function database(): array
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            $rows = DB::select('SELECT table_name AS tname, table_rows AS trows, (data_length + index_length) AS tsize
                FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY tsize DESC');
            // property names can come back upper-cased on some MySQL setups — read them case-insensitively
            $tables = array_map(function ($r) {
                $r = array_change_key_case((array) $r, CASE_LOWER);

                return ['name' => (string) ($r['tname'] ?? ''), 'rows' => (int) ($r['trows'] ?? 0), 'size' => (int) ($r['tsize'] ?? 0)];
            }, $rows);

            return ['size' => array_sum(array_column($tables, 'size')), 'tables' => array_slice($tables, 0, 8)];
        }
        if ($driver === 'pgsql') {
            $rows = DB::select("SELECT relname AS tname, n_live_tup AS trows, pg_total_relation_size(relid) AS tsize
                FROM pg_stat_user_tables ORDER BY tsize DESC");
            $tables = array_map(fn ($r) => ['name' => (string) $r->tname, 'rows' => (int) $r->trows, 'size' => (int) $r->tsize], $rows);
            $size = (int) (DB::selectOne('SELECT pg_database_size(current_database()) AS b')->b ?? 0);

            return ['size' => $size ?: array_sum(array_column($tables, 'size')), 'tables' => array_slice($tables, 0, 8)];
        }
        if ($driver === 'sqlite') {
            $file = DB::connection()->getConfig('database');
            $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
                ->map(fn ($t) => ['name' => $t->name, 'rows' => DB::table($t->name)->count(), 'size' => null])
                ->sortByDesc('rows')->take(8)->values()->all();

            return ['size' => is_file($file) ? filesize($file) : null, 'tables' => $tables];
        }

        return ['size' => null, 'tables' => []];
    }

    private static function dirSize(string $dir): ?int
    {
        if (!is_dir($dir)) {
            return null;
        }
        $size = 0;
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && !$file->isLink()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Throwable) {
            // unreadable folder: return what was counted
        }

        return $size;
    }

    private static function cpus(): ?int
    {
        if (@is_readable('/proc/cpuinfo')) {
            return substr_count((string) @file_get_contents('/proc/cpuinfo'), 'processor') ?: null;
        }

        return null;
    }

    /** @return array{total: int, available: int}|null  (Linux only) */
    private static function memory(): ?array
    {
        if (!@is_readable('/proc/meminfo')) {
            return null;
        }
        $info = (string) @file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $info, $t);
        preg_match('/MemAvailable:\s+(\d+)/', $info, $a);

        return isset($t[1], $a[1]) ? ['total' => (int) $t[1] * 1024, 'available' => (int) $a[1] * 1024] : null;
    }

    public static function bytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $v = (float) $bytes;
        while ($v >= 1024 && $i < count($units) - 1) {
            $v /= 1024;
            $i++;
        }

        return ($i ? number_format($v, 1, '.', ' ') : (int) $v) . ' ' . $units[$i];
    }
}
