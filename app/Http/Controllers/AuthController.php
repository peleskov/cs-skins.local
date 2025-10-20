<?php

namespace App\Http\Controllers;

use Log;
use Exception;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

            Auth::guard('client')->login($client, true);
            
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

    public function logout()
    {
        Auth::guard('client')->logout();
        return redirect()->route('home')->with('success', 'Вы успешно вышли из системы.');
    }
}