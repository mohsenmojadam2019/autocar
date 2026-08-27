<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BackupService
{
    /** Creates a compressed logical database backup without exposing DB credentials to a shell process. */
    public function create(): string
    {
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory, 0700, true);
        $path = $directory.'/autocar-'.now()->format('Ymd-His').'.jsonl.gz';
        $runId = DB::table('backup_runs')->insertGetId(['status' => 'running', 'path' => $path, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $handle = gzopen($path, 'wb9');
        if ($handle === false) {
            throw new RuntimeException('ساخت فایل Backup ممکن نیست.');
        }

        try {
            gzwrite($handle, json_encode(['format' => 'autocar-logical-backup-v1', 'created_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR)."\n");
            foreach (Schema::getTableListing() as $table) {
                if ($table === 'backup_runs') {
                    continue;
                }
                foreach (DB::table($table)->cursor() as $row) {
                    gzwrite($handle, json_encode(['table' => $table, 'row' => (array) $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
                }
            }
            gzclose($handle);
            $size = filesize($path) ?: 0;
            $checksum = hash_file('sha256', $path) ?: null;
            DB::table('backup_runs')->where('id', $runId)->update(['status' => 'completed', 'size_bytes' => $size, 'checksum' => $checksum, 'finished_at' => now(), 'updated_at' => now()]);

            return $path;
        } catch (\Throwable $exception) {
            @gzclose($handle);
            DB::table('backup_runs')->where('id', $runId)->update(['status' => 'failed', 'message' => mb_substr($exception->getMessage(), 0, 1000), 'finished_at' => now(), 'updated_at' => now()]);
            throw $exception;
        }
    }

    /** Restores a trusted logical backup in dependency-safe mode; intended for explicit CLI disaster recovery only. */
    public function restore(string $path): void
    {
        if (! is_file($path) || ! str_ends_with($path, '.jsonl.gz')) {
            throw new RuntimeException('فایل Backup معتبر نیست.');
        }
        $handle = gzopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('خواندن Backup ممکن نیست.');
        }
        $header = json_decode((string) gzgets($handle), true);
        if (($header['format'] ?? null) !== 'autocar-logical-backup-v1') {
            gzclose($handle);
            throw new RuntimeException('فرمت Backup پشتیبانی نمی‌شود.');
        }

        DB::transaction(function () use ($handle): void {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys=OFF');
            }
            try {
                $tables = array_values(array_filter(Schema::getTableListing(), fn ($table) => $table !== 'backup_runs'));
                foreach (array_reverse($tables) as $table) {
                    DB::table($table)->delete();
                }
                $buffers = [];
                while (! gzeof($handle)) {
                    $line = trim((string) gzgets($handle));
                    if ($line === '') {
                        continue;
                    }
                    $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                    $table = $record['table'] ?? null;
                    if (! $table || ! in_array($table, $tables, true)) {
                        continue;
                    }
                    $buffers[$table][] = $record['row'];
                    if (count($buffers[$table]) >= 200) {
                        DB::table($table)->insert($buffers[$table]);
                        $buffers[$table] = [];
                    }
                }
                foreach ($buffers as $table => $rows) {
                    if ($rows !== []) {
                        DB::table($table)->insert($rows);
                    }
                }
            } finally {
                if ($driver === 'mysql') {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                } elseif ($driver === 'sqlite') {
                    DB::statement('PRAGMA foreign_keys=ON');
                }
                gzclose($handle);
            }
        });
    }
}
