<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use App\Models\Referral;
use App\Services\LosReferidosService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackPartnerAttribution
{
    public function handle(Request $request, Closure $next)
    {
        $partnerId = $request->cookie('lr_partner_id');

        if (!$partnerId || !Auth::guard('client')->check()) {
            return $next($request);
        }

        // Если уже обработали эту cookie в текущей сессии — пропускаем
        if (session('lr_attributed_partner') == $partnerId) {
            return $next($request);
        }

        $client = Auth::guard('client')->user();
        $linkId = $request->cookie('lr_link_id');

        $partner = Partner::find($partnerId);

        if (!$partner || !$partner->is_active) {
            return $next($request);
        }

        $existingReferral = $client->referral;

        if (!$existingReferral) {
            $referral = Referral::create([
                'partner_id' => $partner->id,
                'client_id' => $client->id,
                'link_id' => $linkId ?: null,
            ]);
            app(LosReferidosService::class)->sendRegistration($referral);
        } elseif ($existingReferral->partner_id != $partner->id) {
            $existingReferral->update([
                'partner_id' => $partner->id,
                'link_id' => $linkId ?: null,
            ]);
            $existingReferral->refresh();
            app(LosReferidosService::class)->sendRegistration($existingReferral);
        }

        // Запоминаем в сессии, чтобы не проверять на каждый запрос
        session(['lr_attributed_partner' => $partnerId]);

        return $next($request);
    }
}
