<?php

namespace App\Exports;

use App\Models\CaseOpen;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CaseOpensExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected Carbon $from;
    protected Carbon $to;

    public function __construct(string $dateFrom, string $dateTo)
    {
        $this->from = Carbon::parse($dateFrom)->startOfDay();
        $this->to = Carbon::parse($dateTo)->endOfDay();
    }

    public function collection()
    {
        return CaseOpen::with(['client', 'case', 'inventoryItem'])
            ->whereBetween('created_at', [$this->from, $this->to])
            ->orderBy('created_at')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Клиент ID',
            'Клиент',
            'Кейс ID',
            'Кейс',
            'Оплачено',
            'С баланса',
            'С бонусов',
            'Бесплатно',
            'Дата',
        ];
    }

    public function map($open): array
    {
        return [
            $open->id,
            $open->client_id,
            $open->client?->name ?? '',
            $open->case_id,
            $open->case?->name ?? '',
            $open->price_paid,
            $open->balance_used,
            $open->bonus_balance_used,
            $open->is_free ? 'Да' : 'Нет',
            $open->created_at->format('d.m.Y H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
