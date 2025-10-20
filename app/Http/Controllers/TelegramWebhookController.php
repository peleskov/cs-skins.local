<?php

namespace App\Http\Controllers;

use TelegramBot\Api\Types\Update;
use Exception;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use TelegramBot\Api\BotApi as TelegramApi;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            $telegram = new TelegramApi(config('services.telegram.bot_token'));

            // Получаем JSON данные webhook
            $webhookData = $request->getContent();
            $update = Update::fromResponse(json_decode($webhookData, true));

            if (!$update->getMessage()) {
                return response('OK');
            }

            $message = $update->getMessage();
            $chatId = $message->getChat()->getId();
            $text = trim($message->getText());

            Log::channel('notifications')->info('TELEGRAM_WEBHOOK', [
                'chat_id' => $chatId,
                'text' => $text,
                'from' => [
                    'id' => $message->getFrom()->getId(),
                    'username' => $message->getFrom()->getUsername(),
                    'first_name' => $message->getFrom()->getFirstName()
                ]
            ]);

            // Обрабатываем команду /start
            if ($text === '/start') {
                $this->handleStartCommand($telegram, $chatId);
            }
            // Проверяем, если это код с префиксом CODE_
            elseif (str_starts_with($text, 'CODE_')) {
                $code = substr($text, 5); // Убираем префикс CODE_
                $this->handleVerification($telegram, $chatId, $code, $message);
            }
            // Если это не команда и не код, показываем подсказку
            elseif (!str_starts_with($text, '/')) {
                $this->handleInvalidInput($telegram, $chatId);
            }

            return response('OK');

        } catch (Exception $e) {
            Log::channel('notifications')->error('TELEGRAM_WEBHOOK_ERROR', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response('Error', 500);
        }
    }

    private function handleStartCommand(TelegramApi $telegram, string $chatId): void
    {
        Log::channel('notifications')->info('HANDLING_START_COMMAND', [
            'chat_id' => $chatId
        ]);

        // Проверяем, есть ли уже пользователь с таким telegram_id
        $client = Client::where('telegram_id', $chatId)->first();

        if ($client) {
            Log::channel('notifications')->info('EXISTING_CLIENT_FOUND', [
                'chat_id' => $chatId,
                'client_id' => $client->id
            ]);

            $telegram->sendMessage(
                $chatId,
                "👋 Привет, {$client->name}!\n\n" .
                "✅ Ваш аккаунт уже подтвержден.\n" .
                "Вы будете получать уведомления о заказах и аукционах.",
                'HTML'
            );
        } else {
            Log::channel('notifications')->info('NEW_USER_WELCOME', [
                'chat_id' => $chatId
            ]);

            $telegram->sendMessage(
                $chatId,
                "👋 <b>Добро пожаловать в CS-Skins Bot!</b>\n\n" .
                "Для подключения уведомлений отправьте мне код верификации.\n\n" .
                "📝 <b>Как получить код:</b>\n" .
                "1. Войдите в личный кабинет на сайте\n" .
                "2. Нажмите 'Подключить Telegram'\n" .
                "3. Скопируйте код и отправьте его мне\n\n" .
                "Код должен быть в формате: <code>CODE_XXXXXXXX</code>",
                'HTML'
            );
        }
    }

    private function handleInvalidInput(TelegramApi $telegram, string $chatId): void
    {
        $telegram->sendMessage(
            $chatId,
            "❓ Не понимаю эту команду.\n\n" .
            "Пожалуйста, отправьте код верификации в формате:\n" .
            "<code>CODE_XXXXXXXX</code>\n\n" .
            "Получить код можно в личном кабинете на сайте.",
            'HTML'
        );
    }

    private function handleVerification(TelegramApi $telegram, string $chatId, string $code, $message): void
    {
        Log::channel('notifications')->info('HANDLE_VERIFICATION_START', [
            'chat_id' => $chatId,
            'code' => $code
        ]);

        // Формируем полный код с префиксом
        $fullCode = 'CODE_' . $code;

        // Найдем клиента по коду верификации
        $client = Client::where('verification_code', $fullCode)
            ->where('verification_expires_at', '>', now())
            ->first();

        Log::channel('notifications')->info('CLIENT_SEARCH_RESULT', [
            'full_code' => $fullCode,
            'client_found' => $client ? true : false,
            'client_id' => $client ? $client->id : null
        ]);

        if (!$client) {
            $telegram->sendMessage(
                $chatId,
                '❌ Неверный или истекший код верификации. Получите новый код в личном кабинете.',
                'HTML'
            );
            return;
        }

        // Сохраняем telegram_id, username и очищаем код
        $telegramUsername = $message->getFrom()->getUsername();
        $client->update([
            'telegram_id' => $chatId,
            'telegram_username' => $telegramUsername,
            'verification_code' => null,
            'verification_expires_at' => null,
            'is_verified' => true
        ]);

        $telegram->sendMessage(
            $chatId,
            "✅ <b>Аккаунт подтвержден!</b>\n\n" .
            "Привет, {$client->name}!\n" .
            "Telegram уведомления активированы. Вы будете получать уведомления о заказах, аукционах и изменениях баланса.",
            'HTML'
        );

        Log::channel('notifications')->info('TELEGRAM_VERIFIED', [
            'client_id' => $client->id,
            'telegram_id' => $chatId,
            'telegram_username' => $telegramUsername,
            'client_name' => $client->name
        ]);
    }
}
