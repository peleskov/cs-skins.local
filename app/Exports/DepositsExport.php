<?php

namespace App\Exports;

use App\Models\Payment;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DepositsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
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
        return Payment::with('client')
            ->where('status', 'completed')
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
            'Сумма',
            'Статус',
            'Платёжная система',
            'Дата',
        ];
    }

    public function map($payment): array
    {
        return [
            $payment->id,
            $payment->client_id,
            $payment->client?->name ?? '',
            $payment->amount,
            $payment->status,
            $payment->payment_system ?? '',
            $payment->created_at->format('d.m.Y H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
