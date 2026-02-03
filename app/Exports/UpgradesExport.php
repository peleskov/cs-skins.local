<?php

namespace App\Exports;

use App\Models\Upgrade;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UpgradesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
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
        return Upgrade::with('client')
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
            'Ставка',
            'Цель',
            'Шанс %',
            'Результат',
            'Дата',
        ];
    }

    public function map($upgrade): array
    {
        return [
            $upgrade->id,
            $upgrade->client_id,
            $upgrade->client?->name ?? '',
            $upgrade->total_bet,
            $upgrade->target_price,
            $upgrade->win_chance,
            $upgrade->result === 'win' ? 'Выигрыш' : 'Проигрыш',
            $upgrade->created_at->format('d.m.Y H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
