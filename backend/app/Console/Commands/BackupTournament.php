<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupTournament extends Command
{
    protected $signature = 'tournament:backup';

    protected $description = '备份 SQLite 比赛数据库';

    public function handle(): int
    {
        $source = database_path('database.sqlite');
        if (! File::exists($source)) {
            $this->error('SQLite 数据库不存在');

            return self::FAILURE;
        }
        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);
        $target = $directory.'/milk-tea-cup-'.now()->format('Ymd-His').'.sqlite';
        File::copy($source, $target);
        collect(File::files($directory))->sortByDesc(fn ($file) => $file->getMTime())->slice(30)->each(fn ($file) => File::delete($file->getPathname()));
        $this->info("备份完成：$target");

        return self::SUCCESS;
    }
}
