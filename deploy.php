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
    ->set('writable_mode', 'skip');

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
    'artisan:db:seed',  // Запускаем сидеры для создания админа
    'artisan:cache:clear',  // Очищаем старые кэши
    'npm:install',
    'npm:run:prod',
    'artisan:optimize',  // Оптимизируем приложение
    'deploy:supervisor',  // Копируем конфиги supervisor
    'deploy:extension_domains',  // Обновляем домены в browser extension
    'deploy:publish',
]);

// Задача копирования конфигов supervisor
desc('Copy supervisor configs');
task('deploy:supervisor', function () {
    // Создаем папку для конфигов если её нет
    run('mkdir -p {{deploy_path}}/shared/supervisor');

    // Копируем конфиги
    upload('supervisor/scheduler.conf', '{{deploy_path}}/shared/supervisor/scheduler.conf');
    upload('supervisor/horizon.conf', '{{deploy_path}}/shared/supervisor/horizon.conf');
    upload('supervisor/reverb.conf', '{{deploy_path}}/shared/supervisor/reverb.conf');

    // Обновляем пути в конфигах на продакшн пути
    run('sed -i "s|/mnt/nvme/www/html/cs-skins.local|{{deploy_path}}/current|g" {{deploy_path}}/shared/supervisor/*.conf');

    writeln('✅ Конфиги supervisor скопированы');
});

// Задача установки конфигов в систему
desc('Install supervisor configs');
task('deploy:supervisor_install', function () {
    // Копируем в системную папку (требует sudo прав)
    $schedulerExists = test('[ -f /etc/supervisor/conf.d/cs_skins_scheduler.conf ]');
    $horizonExists = test('[ -f /etc/supervisor/conf.d/cs_skins_horizon.conf ]');
    $reverbExists = test('[ -f /etc/supervisor/conf.d/cs_skins_reverb.conf ]');

    if (!$schedulerExists || !$horizonExists || !$reverbExists) {
        writeln('⚠️  Необходимо установить supervisor конфиги:');
        writeln('sudo cp {{deploy_path}}/shared/supervisor/*.conf /etc/supervisor/conf.d/');
        writeln('sudo supervisorctl reread && sudo supervisorctl update');
        writeln('sudo supervisorctl start cs_skins_scheduler cs_skins_horizon cs_skins_reverb');
    } else {
        writeln('ℹ️  Supervisor конфиги уже установлены');
    }
});

// Задача замены доменов в browser extension
desc('Update browser extension domains');
task('deploy:extension_domains', function () {
    // Заменяем домены в browser extension для прода
    run('find {{release_path}}/browser-extension -type f \( -name "*.html" -o -name "*.js" -o -name "*.json" \) -exec sed -i "s/cs-skins\.s1temaker\.ru/cs-skins.pro/g" {} \;');
    writeln('✅ Домены в browser extension обновлены для прода');
});

// Задачи после успешного деплоя
after('deploy:success', 'deploy:supervisor_install');
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