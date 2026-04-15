<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Триал',
                'price' => 10,
                'duration_days' => 3,
                'is_trial' => true,
                'sort_order' => 1,
            ],
            [
                'name' => '1 неделя',
                'price' => 349,
                'duration_days' => 7,
                'is_trial' => false,
                'sort_order' => 2,
            ],
            [
                'name' => '2 недели',
                'price' => 599,
                'duration_days' => 14,
                'is_trial' => false,
                'sort_order' => 3,
            ],
            [
                'name' => '1 месяц',
                'price' => 999,
                'duration_days' => 30,
                'is_trial' => false,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}
