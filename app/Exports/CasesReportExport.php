<?php

namespace App\Exports;

use App\Models\CaseModel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CasesReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function collection()
    {
        return CaseModel::query()
            ->select('cases.*')
            ->selectRaw('(SELECT COUNT(*) FROM case_opens WHERE case_opens.case_id = cases.id) as opens_count')
            ->selectRaw('(SELECT COALESCE(SUM(price_paid), 0) FROM case_opens WHERE case_opens.case_id = cases.id) as opens_sum_price_paid')
            ->selectRaw('(SELECT COALESCE(SUM(cii.price), 0) FROM case_inventory_items cii
                INNER JOIN case_opens co ON co.id = cii.source_id
                WHERE cii.source_type = \'case_open\' AND co.case_id = cases.id) as total_won')
            ->orderByDesc('opens_count')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Кейс',
            'Цена',
            'Открытий',
            'Выручка',
            'Выплачено',
            'Средний чек',
            'Коэф. выплат %',
        ];
    }

    public function map($case): array
    {
        $avgCheck = $case->opens_count > 0
            ? round($case->opens_sum_price_paid / $case->opens_count, 2)
            : 0;

        $payoutRatio = $case->opens_sum_price_paid > 0
            ? round(($case->total_won / $case->opens_sum_price_paid) * 100, 1)
            : 0;

        return [
            $case->id,
            $case->name,
            $case->price,
            $case->opens_count ?? 0,
            $case->opens_sum_price_paid ?? 0,
            $case->total_won ?? 0,
            $avgCheck,
            $payoutRatio,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
