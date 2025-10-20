<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use TelegramBot\Api\BotApi as TelegramApi;

class SetupTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:setup-webhook {action=set : set or remove}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup or remove Telegram webhook';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        $botToken = config('services.telegram.bot_token');

        if (!$botToken) {
            $this->error('TELEGRAM_BOT_TOKEN не настроен в .env');
            return 1;
        }

        try {
            $telegram = new TelegramApi($botToken);

            if ($action === 'remove') {
                $response = $telegram->deleteWebhook();
                $this->info('Webhook удален');
                return 0;
            }

            $webhookUrl = rtrim(config('app.url'), '/') . '/api/telegram/webhook';

            $this->info("Устанавливаем webhook: $webhookUrl");

            $response = $telegram->setWebhook(
                $webhookUrl,
                null, // certificate
                null, // ip_address
                40,   // max_connections
                ['message', 'callback_query'], // allowed_updates
                true  // drop_pending_updates
            );

            if ($response) {
                $this->info('✅ Webhook успешно установлен!');

                // Проверяем информацию о webhook
                $info = $telegram->getWebhookInfo();
                $this->info('URL: ' . $info->getUrl());
                $this->info('Pending updates: ' . $info->getPendingUpdateCount());

                if ($info->getLastErrorDate()) {
                    $this->warn('Last error: ' . $info->getLastErrorMessage());
                }
            } else {
                $this->error('Не удалось установить webhook');
                return 1;
            }

        } catch (Exception $e) {
            $this->error('Ошибка: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
