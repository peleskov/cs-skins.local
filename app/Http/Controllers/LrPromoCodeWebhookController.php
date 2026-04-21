<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\Promocode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LrPromoCodeWebhookController extends Controller
{
    /**
     * Приём webhook о создании промокода от LosReferidos
     * POST /api/lr/promo-codes
     */
    public function store(Request $request): JsonResponse
    {
        // Проверка подписи
        $providedHash = (string) $request->header('X-Adv-Hash', '');
        $expectedHash = (string) config('services.losreferidos.hash');

        if ($expectedHash === '' || ! hash_equals($expectedHash, $providedHash)) {
            Log::channel('losreferidos')->warning('LR webhook: неверный X-Adv-Hash', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Валидация тела
        $data = $request->validate([
            'event' => 'required|string|in:promo_code_created',
            'advertiser_id' => 'required|integer',
            'promo_code' => 'required|array',
            'promo_code.code' => 'required|string|max:100',
            'promo_code.offer_id' => 'nullable|integer',
            'promo_code.partner_id' => 'required|integer',
            'promo_code.type' => 'required|string|in:fixed,percent',
            'promo_code.value' => 'required|numeric|min:0',
            'promo_code.min_deposit' => 'nullable|numeric|min:0',
            'promo_code.valid_from' => 'nullable|date',
            'promo_code.valid_to' => 'nullable|date',
            'promo_code.total_usage_limit' => 'nullable|integer|min:1',
            'promo_code.per_user_usage_limit' => 'nullable|integer|min:1',
            'promo_code.is_active' => 'required|boolean',
        ]);

        // Проверка advertiser_id
        $expectedAdvId = (int) config('services.losreferidos.adv_id');
        if ($expectedAdvId > 0 && (int) $data['advertiser_id'] !== $expectedAdvId) {
            throw ValidationException::withMessages([
                'advertiser_id' => ['Unknown advertiser_id'],
            ]);
        }

        $pc = $data['promo_code'];

        // Партнёр должен существовать на нашей стороне
        $partner = Partner::find($pc['partner_id']);
        if (! $partner) {
            throw ValidationException::withMessages([
                'partner_id' => ['Partner is not registered on our side'],
            ]);
        }

        // Уникальность кода
        if (Promocode::where('code', $pc['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => ['Promo code already exists'],
            ]);
        }

        $promocode = Promocode::create([
            'code' => $pc['code'],
            'type' => $pc['type'],
            'value' => $pc['value'],
            'min_deposit' => $pc['min_deposit'] ?? 0,
            'max_uses' => $pc['total_usage_limit'] ?? null,
            'max_uses_per_user' => $pc['per_user_usage_limit'] ?? 1,
            'starts_at' => $pc['valid_from'] ?? null,
            'expires_at' => $pc['valid_to'] ?? null,
            'is_active' => (bool) $pc['is_active'],
            'partner_id' => $partner->id,
            'lr_offer_id' => $pc['offer_id'] ?? null,
        ]);

        Log::channel('losreferidos')->info('LR webhook: промокод создан', [
            'promocode_id' => $promocode->id,
            'code' => $promocode->code,
            'partner_id' => $partner->id,
        ]);

        return response()->json(['status' => 'ok', 'promocode_id' => $promocode->id], 200);
    }
}
