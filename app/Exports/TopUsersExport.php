<?php

namespace App\Exports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TopUsersExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function collection()
    {
        return Client::query()
            ->select('clients.*')
            ->selectRaw('(SELECT COUNT(*) FROM case_opens WHERE case_opens.client_id = clients.id) as case_opens_count')
            ->selectRaw('(SELECT COALESCE(SUM(price_paid), 0) FROM case_opens WHERE case_opens.client_id = clients.id) as total_spent')
            ->selectRaw('(SELECT COALESCE(SUM(cii.price), 0) FROM case_inventory_items cii WHERE cii.client_id = clients.id AND cii.source_type = \'case_open\') as total_won')
            ->selectRaw('(SELECT COUNT(*) FROM upgrades WHERE upgrades.client_id = clients.id) as upgrades_count')
            ->selectRaw('(SELECT COUNT(*) FROM upgrades WHERE upgrades.client_id = clients.id AND upgrades.result = \'win\') as upgrades_wins')
            ->havingRaw('case_opens_count > 0 OR upgrades_count > 0')
            ->orderByDesc('case_opens_count')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Пользователь',
            'Открытий',
            'Потрачено',
            'Выиграно',
            'Удача %',
            'Апгрейдов',
            'Побед',
            'Winrate %',
        ];
    }

    public function map($client): array
    {
        $luck = $client->total_spent > 0
            ? round(($client->total_won / $client->total_spent) * 100, 1)
            : 0;

        $winrate = $client->upgrades_count > 0
            ? round(($client->upgrades_wins / $client->upgrades_count) * 100, 1)
            : 0;

        return [
            $client->id,
            $client->name,
            $client->case_opens_count ?? 0,
            $client->total_spent ?? 0,
            $client->total_won ?? 0,
            $luck,
            $client->upgrades_count ?? 0,
            $client->upgrades_wins ?? 0,
            $winrate,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
