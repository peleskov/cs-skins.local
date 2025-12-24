<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Команда для показа упакованных расширений
Artisan::command('extension:list {--path=storage/app/extensions : Directory to list}', function () {
    $path = $this->option('path');
    
    if (!str_starts_with($path, '/')) {
        $path = base_path($path);
    }
    
    if (!is_dir($path)) {
        $this->error("❌ Директория не найдена: {$path}");
        return 1;
    }
    
    $files = glob($path . '/*.zip');
    
    if (empty($files)) {
        $this->warn("📦 Нет упакованных расширений в {$path}");
        return 0;
    }
    
    $this->info("📦 Упакованные расширения в {$path}:");
    $this->newLine();
    
    foreach ($files as $file) {
        $size = filesize($file);
        $date = date('Y-m-d H:i:s', filemtime($file));
        $sizeFormatted = $size > 1024 ? round($size/1024, 1) . ' KB' : $size . ' B';
        
        $this->line(sprintf(
            '<fg=green>%s</> <fg=yellow>%s</> <fg=cyan>%s</>',
            basename($file),
            $sizeFormatted,
            $date
        ));
    }
    
    $this->newLine();
    $this->comment('💡 Используйте: php artisan extension:pack --help для создания новых архивов');
    
})->purpose('List packed browser extensions');

// Планировщик задач
Schedule::job(new \App\Jobs\ProcessSkinScreenshots())
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();

// Обновление курсов валют каждый день в 23:55
Schedule::command('currency:update')
    ->dailyAt('23:55')
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

// Обновление цен Steam предметов каждый час
Schedule::command('prices:update')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

// Завершение истекших аукционов каждую минуту
Schedule::command('auctions:complete-expired')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

// Релиз заказов после окончания Steam холда каждые 5 минут
Schedule::job(new \App\Jobs\ReleaseSettledOrders())
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping();
