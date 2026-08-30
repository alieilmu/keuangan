<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Metrik kesehatan sistem yang dibaca langsung dari server yang sedang
 * berjalan -- bukan data simulasi. Setiap sumber dibungkus try/catch
 * sendiri: kegagalan satu sumber (mis. Redis sedang down) tidak boleh
 * membuat seluruh halaman health check ikut gagal.
 */
class SystemHealthService
{
    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'app' => $this->appMetrics(),
            'database' => $this->databaseMetrics(),
            'redis' => $this->redisMetrics(),
            'disk' => $this->diskMetrics(),
        ];
    }

    /** @return array<string, mixed> */
    private function appMetrics(): array
    {
        $memoryLimit = ini_get('memory_limit');

        return [
            'ok' => true,
            'php_version' => PHP_VERSION,
            'memory_used_mb' => round(memory_get_usage(true) / 1024 / 1024, 1),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
            'memory_limit' => $memoryLimit,
            'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status(false) !== false,
        ];
    }

    /** @return array<string, mixed> */
    private function databaseMetrics(): array
    {
        try {
            $database = DB::connection()->getDatabaseName();

            $size = DB::selectOne("
                SELECT
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size_mb,
                    COUNT(*) AS table_count
                FROM information_schema.tables
                WHERE table_schema = ?
            ", [$database]);

            $connections = (int) (DB::selectOne('SHOW STATUS LIKE "Threads_connected"')->Value ?? 0);
            $maxConnections = (int) (DB::selectOne('SHOW VARIABLES LIKE "max_connections"')->Value ?? 0);

            // information_schema mengembalikan nama kolom dalam huruf besar
            // (TABLE_NAME) kecuali diberi alias eksplisit -- tanpa alias,
            // akses properti lowercase di bawah akan gagal dengan
            // "Undefined property".
            $rowCounts = collect(DB::select("
                SELECT table_name AS name, table_rows AS row_count
                FROM information_schema.tables
                WHERE table_schema = ? AND table_rows IS NOT NULL
                ORDER BY table_rows DESC
                LIMIT 5
            ", [$database]))->map(fn ($row) => [
                'table' => $row->name,
                'rows' => (int) $row->row_count,
            ]);

            return [
                'ok' => true,
                'size_mb' => (float) ($size->size_mb ?? 0),
                'table_count' => (int) ($size->table_count ?? 0),
                'connections' => $connections,
                'max_connections' => $maxConnections,
                'connection_usage_pct' => $maxConnections > 0
                    ? round(($connections / $maxConnections) * 100, 1) : 0,
                'largest_tables' => $rowCounts,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private function redisMetrics(): array
    {
        try {
            $info = Redis::connection()->client()->info();

            // Queue::size() menangani prefix key Redis secara internal --
            // lebih aman daripada menebak nama key mentah secara manual.
            $queueLength = (int) Queue::connection('redis')->size('default');

            return [
                'ok' => true,
                'used_memory_mb' => isset($info['used_memory'])
                    ? round(((int) $info['used_memory']) / 1024 / 1024, 1) : null,
                'connected_clients' => (int) ($info['connected_clients'] ?? 0),
                'uptime_days' => isset($info['uptime_in_seconds'])
                    ? round(((int) $info['uptime_in_seconds']) / 86400, 1) : null,
                'queue_length' => $queueLength,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Kapasitas disk sebagaimana terlihat dari dalam container app. Ini
     * bukan "VPS host" secara literal (container berjalan di atas overlay
     * filesystem-nya sendiri), tetapi merepresentasikan disk yang benar-benar
     * dipakai storage aplikasi (upload dokumen, log) -- metrik paling
     * relevan yang bisa diamati tanpa akses ke luar container.
     */
    private function diskMetrics(): array
    {
        try {
            $total = disk_total_space('/var/www/html');
            $free = disk_free_space('/var/www/html');

            if ($total === false || $free === false) {
                throw new \RuntimeException('Tidak bisa membaca kapasitas disk.');
            }

            $used = $total - $free;

            return [
                'ok' => true,
                'total_gb' => round($total / 1024 / 1024 / 1024, 1),
                'used_gb' => round($used / 1024 / 1024 / 1024, 1),
                'used_pct' => round(($used / $total) * 100, 1),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
