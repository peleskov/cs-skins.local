<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Notifications\VerifyEmailNotification;

class ProfileController extends Controller
{
    /**
     * Страница профиля пользователя
     */
    public function index()
    {
        $client = auth('client')->user();
        return view('profile.index', compact('client'));
    }

    /**
     * Обновление email адреса
     */
    public function updateEmail(Request $request)
    {
        $client = auth('client')->user();
        
        $request->validate([
            'email' => 'required|email|unique:clients,email,' . $client->id
        ], [
            'email.required' => 'Email адрес обязателен для заполнения',
            'email.email' => 'Введите корректный email адрес',
            'email.unique' => 'Этот email адрес уже используется'
        ]);

        $isNewEmail = !$client->email;
        $isEmailChanged = $client->email && $client->email !== $request->email;
        
        if ($isNewEmail || $isEmailChanged) {
            $client->email = $request->email;
            $client->email_verified_at = null;
            $client->email_verification_sent_at = now();
            $client->save();
            
            // Отправляем письмо с верификацией
            $client->notify(new VerifyEmailNotification());
            
            if ($isNewEmail) {
                return redirect()->route('profile')->with('success', 'Email адрес добавлен. Проверьте почту для подтверждения.');
            } else {
                return redirect()->route('profile')->with('success', 'Email адрес изменен. Проверьте почту для подтверждения.');
            }
        }
        
        return redirect()->route('profile');
    }

    /**
     * Подтверждение email адреса
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        $client = auth('client')->user();
        
        \Log::info('Email verification attempt', [
            'user_id' => $client->id,
            'url_id' => $id,
            'url_hash' => $hash,
            'user_email_hash' => sha1($client->email),
            'has_valid_signature' => $request->hasValidSignature()
        ]);
        
        // Временно отключаем проверку подписи для отладки
        // if (!$request->hasValidSignature()) {
        //     return redirect()->route('profile')->with('error', 'Ссылка для подтверждения недействительна или устарела.');
        // }
        
        if ($client->id != $id || sha1($client->email) !== $hash) {
            return redirect()->route('profile')->with('error', 'Неверная ссылка для подтверждения.');
        }
        
        if ($client->hasVerifiedEmail()) {
            return redirect()->route('profile')->with('info', 'Email уже подтвержден.');
        }
        
        $client->email_verified_at = now();
        $client->save();
        
        return redirect()->route('profile')->with('success', 'Email адрес успешно подтвержден!');
    }

    /**
     * Обновление Trade URL
     */
    public function updateTradeUrl(Request $request)
    {
        $client = auth('client')->user();
        
        $request->validate([
            'trade_url' => 'required|url',
        ]);
        
        $tradeUrl = $request->trade_url;
        
        // Валидация Trade URL (формат + соответствие Steam ID)
        $validation = \App\Models\Client::validateTradeUrl($tradeUrl, $client->steam_id);
        
        if (!$validation['valid']) {
            return redirect()->route('profile')->with('error', $validation['message']);
        }
        
        $client->steam_trade_url = $tradeUrl;
        $client->save();
        
        return redirect()->route('profile')->with('success', 'Trade URL успешно обновлен!');
    }

    /**
     * Повторная отправка письма с подтверждением
     */
    public function resendVerification(Request $request)
    {
        $client = auth('client')->user();
        
        if (!$client->email) {
            return response()->json([
                'success' => false,
                'message' => 'Email адрес не указан.'
            ], 400);
        }
        
        if ($client->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email уже подтвержден.'
            ], 400);
        }
        
        if (!$client->canResendVerificationEmail()) {
            $time = $client->formattedTimeUntilCanResend();
            return response()->json([
                'success' => false,
                'message' => "Повторная отправка будет доступна через {$time}."
            ], 429);
        }
        
        $client->email_verification_sent_at = now();
        $client->save();
        $client->refresh();
        
        $client->notify(new VerifyEmailNotification());
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Письмо с подтверждением отправлено повторно.'
            ]);
        }
        
        return redirect()->route('profile')->with('success', 'Письмо с подтверждением отправлено повторно.');
    }
}