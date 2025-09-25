<?php

namespace Deployer;

require 'recipe/laravel.php';
require 'contrib/npm.php';

// Project name
set('application', 'cs-skins');

// Project repository
set('repository', 'git@github.com:peleskov/cs-skins.local.git');

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', false);

// НЕ используем shared файл .env, так как будем копировать .env.prod при каждом деплое
add('shared_dirs', ['storage', 'public/storage']);

// Writable dirs by web server
add('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
]);

// Hosts - обновите параметры подключения к серверу
host('production')
    ->setHostname('31.97.75.70')  // IP адрес сервера
    ->set('remote_user', 'deployer')  // Пользователь SSH
    ->set('deploy_path', '/var/www/cs-skins.pro')  // Путь на сервере
    ->set('branch', 'main')
    ->set('http_user', 'www-data')  // Пользователь веб-сервера
    ->set('writable_mode', 'chmod')
    ->set('writable_use_sudo', false);

// Задача копирования .env.prod в .env
desc('Copy production environment file');
task('deploy:env', function () {
    // Загружаем локальный .env.prod на сервер
    upload('.env.prod', '{{release_path}}/.env.prod');
    // Копируем в .env
    run('cd {{release_path}} && cp .env.prod .env');
    writeln('✅ Скопирован .env.prod в .env');
});

// Задача для генерации ключа приложения (если нужно)
desc('Generate application key if not exists');
task('artisan:key:generate', function () {
    $envContent = run('cat {{release_path}}/.env');
    if (strpos($envContent, 'APP_KEY=base64:') === false) {
        run('cd {{release_path}} && {{bin/php}} artisan key:generate --ansi');
        writeln('✅ Сгенерирован новый APP_KEY');
    } else {
        writeln('ℹ️ APP_KEY уже установлен');
    }
});

// Задача для перезапуска Horizon
desc('Restart Laravel Horizon');
task('horizon:restart', function () {
    // Проверяем, запущен ли Horizon через supervisord
    $supervisorRunning = test('[ -f /usr/bin/supervisorctl ]');

    if ($supervisorRunning) {
        // Если используется supervisor
        run('sudo supervisorctl restart horizon || true');
        writeln('✅ Laravel Horizon перезапущен через Supervisor');
    } else {
        // Альтернативный метод через artisan
        run('cd {{release_path}} && {{bin/php}} artisan horizon:terminate || true');
        writeln('✅ Laravel Horizon получил сигнал к перезапуску');
    }
});

// Задача для перезапуска Reverb
desc('Restart Laravel Reverb WebSocket Server');
task('reverb:restart', function () {
    // Проверяем, запущен ли Reverb через supervisord
    $supervisorRunning = test('[ -f /usr/bin/supervisorctl ]');

    if ($supervisorRunning) {
        // Если используется supervisor
        run('sudo supervisorctl restart reverb || true');
        writeln('✅ Laravel Reverb перезапущен через Supervisor');
    } else {
        // Альтернативный метод - останавливаем старый процесс и supervisor его перезапустит
        run('pkill -f "artisan reverb:start" || true');
        writeln('✅ Laravel Reverb получил сигнал к перезапуску');
    }
});

// Задача для перезапуска Scheduler (обычно через cron, но можем проверить)
desc('Ensure Laravel Scheduler is running');
task('scheduler:ensure', function () {
    // Проверяем наличие cron задачи для scheduler
    $cronExists = test('crontab -l | grep -q "artisan schedule:run"');

    if (!$cronExists) {
        writeln('⚠️ Внимание: Laravel Scheduler не настроен в cron!');
        writeln('Добавьте в crontab: * * * * * cd {{deploy_path}}/current && {{bin/php}} artisan schedule:run >> /dev/null 2>&1');
    } else {
        writeln('✅ Laravel Scheduler настроен в cron');
    }
});

// Задача для очистки всех кэшей
desc('Clear all Laravel caches');
task('artisan:cache:clear', function () {
    run('cd {{release_path}} && {{bin/php}} artisan cache:clear');
    run('cd {{release_path}} && {{bin/php}} artisan route:clear');
    run('cd {{release_path}} && {{bin/php}} artisan view:clear');
    writeln('✅ Все кэши очищены');
});

// Задача для оптимизации
desc('Optimize Laravel application');
task('artisan:optimize', function () {
    run('cd {{release_path}} && {{bin/php}} artisan config:cache');
    run('cd {{release_path}} && {{bin/php}} artisan route:cache');
    run('cd {{release_path}} && {{bin/php}} artisan view:cache');
    run('cd {{release_path}} && {{bin/php}} artisan event:cache');
    writeln('✅ Приложение оптимизировано');
});

// Build npm assets
task('npm:run:prod', function () {
    run('cd {{release_path}} && npm run build');
    writeln('✅ Фронтенд собран');
});

// Основная задача деплоя
desc('Deploy the application');
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'deploy:env',  // Копируем .env.prod в .env
    'artisan:key:generate',  // Проверяем APP_KEY
    'artisan:storage:link',
    'artisan:migrate',  // Миграции ПЕРЕД очисткой кэша
    'artisan:cache:clear',  // Очищаем старые кэши
    'npm:install',
    'npm:run:prod',
    'artisan:optimize',  // Оптимизируем приложение
    'deploy:publish',
]);

// Задачи после успешного деплоя
after('deploy:success', 'horizon:restart');
after('deploy:success', 'reverb:restart');
after('deploy:success', 'scheduler:ensure');

// Если деплой не удался - разблокируем
after('deploy:failed', 'deploy:unlock');

// Уведомление об успешном деплое
after('deploy:success', function () {
    writeln('🚀 <info>Деплой успешно завершен!</info>');
    writeln('📦 Версия: {{release_path}}');
    writeln('🔗 Текущий релиз: {{deploy_path}}/current');
});