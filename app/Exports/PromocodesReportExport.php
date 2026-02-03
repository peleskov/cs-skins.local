<?php

namespace App\Exports;

use App\Models\Promocode;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PromocodesReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function collection()
    {
        return Promocode::query()
            ->withCount('bonusTransactions')
            ->withSum('bonusTransactions', 'amount')
            ->orderByDesc('bonus_transactions_count')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Код',
            'Тип',
            'Значение',
            'Активен',
            'Использований',
            'Выдано бонусов',
            'Лимит',
            'Конверсия',
        ];
    }

    public function map($promo): array
    {
        $conversion = '—';
        if ($promo->max_uses && $promo->max_uses > 0) {
            $rate = ($promo->bonus_transactions_count / $promo->max_uses) * 100;
            $conversion = number_format($rate, 1) . '%';
        }

        return [
            $promo->id,
            $promo->code,
            $promo->type === 'percent' ? 'Процент' : 'Фиксированный',
            $promo->type === 'percent' ? $promo->value . '%' : $promo->value . ' ₽',
            $promo->is_active ? 'Да' : 'Нет',
            $promo->bonus_transactions_count ?? 0,
            $promo->bonus_transactions_sum_amount ?? 0,
            $promo->max_uses ?? '∞',
            $conversion,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
