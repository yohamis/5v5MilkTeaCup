<?php

namespace App\Console\Commands;

use App\Services\TournamentDataService;
use Illuminate\Console\Command;

class ImportTournament extends Command
{
    protected $signature = 'tournament:import {path} {--append}';

    protected $description = '导入奶茶杯比赛 JSON';

    public function handle(TournamentDataService $service): int
    {
        $path = realpath($this->argument('path'));
        if (! $path) {
            $this->error('文件不存在');

            return self::FAILURE;
        }
        $summary = $service->import(json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR), ! $this->option('append'));
        $this->info("导入完成：{$summary['matches']} 场，{$summary['players']} 名玩家，{$summary['records']} 条记录");

        return self::SUCCESS;
    }
}
