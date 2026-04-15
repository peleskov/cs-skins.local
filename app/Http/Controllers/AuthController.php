<?php

namespace App\Http\Controllers;

use Log;
use Exception;
use App\Models\Client;
use App\Models\LoginHistory;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\Subscription;
use App\Services\LosReferidosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirectToSteam()
    {
        return Socialite::driver('steam')->redirect();
    }

    public function handleSteamCallback(Request $request)
    {
        try {
            $steamUser = Socialite::driver('steam')->user();

            Log::info('Steam user data:', [
                'id' => $steamUser->id,
                'nickname' => $steamUser->nickname,
                'avatar' => $steamUser->avatar
            ]);

            $client = Client::updateOrCreate(
                ['steam_id' => $steamUser->id],
                [
                    'name' => $steamUser->nickname,
                    'steam_avatar' => $steamUser->avatar,
                ]
            );

            // Мердж временного клиента после оплаты подписки на лендинге (сценарий Б)
            $hadActiveSubscription = $client->subscription?->isValid() ?? false;
            $partnerPayment = null;

            if (session('partner_client_id')) {
                $this->mergeReferralClient($client, (int) session('partner_client_id'));
                $partnerPayment = Payment::find(session('partner_payment_id'));
                session()->forget(['partner_client_id', 'partner_payment_id']);
            }

            // Привязка к партнёру по cookie (UTM-трекинг)
            $this->handlePartnerAttribution($request, $client, $hadActiveSubscription, $partnerPayment);

            // Если у клиента установлен код-пароль и функция включена — проверяем кулдаун
            if (!empty($client->pin_code) && $client->premiumFeatureEnabled('pin_code')) {
                $cooldown = (int) ($client->subscription?->settings['pin_code_cooldown'] ?? 0);
                $skipPin = false;

                if ($cooldown > 0 && $client->pin_verified_at) {
                    $skipPin = $client->pin_verified_at->addMinutes($cooldown)->isFuture();
                }

                if (!$skipPin) {
                    session(['pin_code_pending' => true, 'pin_code_client_id' => $client->id]);
                    return redirect()->route('pin-code.form');
                }
            }

            Auth::guard('client')->login($client, true);

            LoginHistory::create([
                'client_id' => $client->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'success',
            ]);

            Log::info('Client logged in:', [
                'client_id' => $client->id,
                'is_logged_in' => Auth::guard('client')->check()
            ]);

            return redirect()->route('profile')->with('success', 'Вы успешно вошли через Steam!');
        } catch (Exception $e) {
            Log::error('Steam auth error: ' . $e->getMessage());
            return redirect()->route('home')->with('error', 'Ошибка авторизации через Steam. Попробуйте снова.');
        }
    }

    /**
     * Привязка клиента к партнёру по cookie + отправка событий в LR
     */
    private function handlePartnerAttribution(Request $request, Client $client, bool $hadActiveSubscription, ?Payment $partnerPayment): void
    {
        $partnerId = $request->cookie('lr_partner_id');
        $linkId = $request->cookie('lr_link_id');

        if (!$partnerId) {
            return;
        }

        $partner = Partner::where('id', $partnerId)->where('is_active', true)->first();
        if (!$partner) {
            return;
        }

        $lrService = app(LosReferidosService::class);
        $existingReferral = $client->referral;

        if (!$existingReferral) {
            // Новый реферал
            $referral = Referral::create([
                'partner_id' => $partner->id,
                'client_id' => $client->id,
                'link_id' => $linkId ?: null,
            ]);
            $lrService->sendRegistration($referral);
        } elseif ($existingReferral->partner_id != $partner->id) {
            // Смена партнёра (last click wins)
            $existingReferral->update([
                'partner_id' => $partner->id,
                'link_id' => $linkId ?: null,
            ]);
            $existingReferral->refresh();
            $lrService->sendRegistration($existingReferral);
        }

        // Если был мердж с лендинга и подписка оплачена — отправляем событие подписки
        if ($partnerPayment && $partnerPayment->isPaid()) {
            $referral = $client->referral()->first();
            if ($referral) {
                if ($hadActiveSubscription) {
                    $lrService->sendRebill($referral, $partnerPayment);
                } else {
                    $lrService->sendSubscription($referral, $partnerPayment);
                }
            }
        }
    }

    /**
     * Перенос данных с временного клиента на реального (сценарий Б — лендинг с оплатой)
     * Переносит подписки и платежи. Рефералы НЕ переносятся — создаются после мерджа по cookie.
     */
    private function mergeReferralClient(Client $realClient, int $tempClientId): void
    {
        $tempClient = Client::find($tempClientId);
        if (!$tempClient || $tempClient->steam_id) {
            return;
        }

        if ($tempClient->id === $realClient->id) {
            return;
        }

        DB::transaction(function () use ($realClient, $tempClient) {
            // Переносим платежи
            Payment::where('client_id', $tempClient->id)->update(['client_id' => $realClient->id]);

            // Подписка — проверяем есть ли активная у реального клиента
            $hasActiveSubscription = Subscription::where('client_id', $realClient->id)
                ->where('is_active', true)
                ->exists();

            if ($hasActiveSubscription) {
                Subscription::where('client_id', $tempClient->id)->delete();
            } else {
                Subscription::where('client_id', $tempClient->id)->update(['client_id' => $realClient->id]);
            }

            // Переносим транзакции
            \App\Models\Transaction::where('client_id', $tempClient->id)->update(['client_id' => $realClient->id]);

            // Удаляем рефералы temp-клиента (не нужны — создадутся по cookie)
            Referral::where('client_id', $tempClient->id)->delete();

            // Удаляем временного клиента
            $tempClient->delete();

            Log::info('Партнёрский клиент смерджен', [
                'temp_client_id' => $tempClient->id,
                'real_client_id' => $realClient->id,
                'had_active_subscription' => $hasActiveSubscription,
            ]);
        });
    }

    public function logout()
    {
        Auth::guard('client')->logout();
        return redirect()->route('home')->with('success', 'Вы успешно вышли из системы.');
    }
}