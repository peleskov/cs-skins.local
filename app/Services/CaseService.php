<?php

namespace App\Services;

use App\Models\CaseModel;
use App\Models\CaseTier;
use App\Models\CaseItem;
use App\Models\ClientInventoryItem;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CaseService
{
    /**
     * Открытие кейса и разыгрыш приза
     */
    public function openCase(CaseModel $case, Client $buyer): ClientInventoryItem
    {
        return DB::transaction(function () use ($case, $buyer) {
            // Обновляем кейс с блокировкой для предотвращения race conditions
            $case = CaseModel::lockForUpdate()->find($case->id);
            
            // Определяем доступные уровни на основе текущего фонда
            $availableTiers = $this->getAvailableTiers($case);
            
            if ($availableTiers->isEmpty()) {
                throw new Exception('Нет доступных уровней для розыгрыша');
            }
            
            // Выбираем уровень по вероятности
            $selectedTier = $this->selectTierByProbability($availableTiers);
            
            // Выбираем предмет из уровня
            $prizeItem = $this->selectItemFromTier($selectedTier);
            
            // Получаем цену приза
            $prizePrice = $prizeItem->getCurrentPrice() ?: 0;
            
            // Вычитаем стоимость приза из фонда (только если фонд больше стоимости)
            if ($case->accumulated_fund >= $prizePrice) {
                $case->decrement('accumulated_fund', $prizePrice);
            }
            
            // Логируем операцию
            Log::info('Case opened', [
                'case_id' => $case->id,
                'buyer_id' => $buyer->id,
                'tier_id' => $selectedTier->id,
                'prize_item_id' => $prizeItem->id,
                'prize_price' => $prizePrice,
                'fund_before' => $case->accumulated_fund + $prizePrice,
                'fund_after' => $case->accumulated_fund,
            ]);
            
            return $prizeItem;
        });
    }
    
    /**
     * Получить доступные уровни на основе фонда кейса
     */
    private function getAvailableTiers(CaseModel $case): \Illuminate\Database\Eloquent\Collection
    {
        return $case->tiers()
            ->where(function ($query) use ($case) {
                // Уровень доступен если фонд >= 2 * цена уровня
                // ИЛИ это самый дешевый уровень (всегда доступен)
                $query->where('price', '<=', $case->accumulated_fund / 2)
                      ->orWhere('price', '=', function ($subQuery) use ($case) {
                          $subQuery->selectRaw('MIN(price)')
                                   ->from('case_tiers')
                                   ->where('case_id', $case->id);
                      });
            })
            ->whereHas('items') // Только уровни с предметами
            ->orderBy('price', 'asc')
            ->get();
    }
    
    /**
     * Выбрать уровень по вероятности
     */
    private function selectTierByProbability(\Illuminate\Database\Eloquent\Collection $tiers): CaseTier
    {
        $totalProbability = $tiers->sum('probability');
        $random = mt_rand(1, $totalProbability * 100) / 100;
        
        $cumulative = 0;
        foreach ($tiers as $tier) {
            $cumulative += $tier->probability;
            if ($random <= $cumulative) {
                return $tier;
            }
        }
        
        // Fallback - возвращаем самый дешевый уровень
        return $tiers->first();
    }
    
    /**
     * Выбрать случайный предмет из уровня и удалить его из кейса
     */
    private function selectItemFromTier(CaseTier $tier): ClientInventoryItem
    {
        $caseItems = $tier->items()->with('inventoryItem')->get();
        
        if ($caseItems->isEmpty()) {
            throw new Exception("Нет доступных предметов в уровне {$tier->name}");
        }
        
        // Выбираем случайный предмет
        $randomCaseItem = $caseItems->random();
        
        // Удаляем предмет из кейса (он больше не может быть разыгран)
        $randomCaseItem->delete();
        
        return $randomCaseItem->inventoryItem;
    }
    
    /**
     * Проверить может ли пользователь купить кейс
     */
    public function canPurchaseCase(CaseModel $case, Client $buyer): bool
    {
        // Проверяем что кейс активен
        if (!$case->is_active) {
            return false;
        }
        
        // Проверяем что у кейса есть уровни с предметами
        if (!$case->tiers()->whereHas('items')->exists()) {
            return false;
        }
        
        // Проверяем баланс пользователя
        if ($buyer->balance < $case->price) {
            return false;
        }
        
        return true;
    }
}