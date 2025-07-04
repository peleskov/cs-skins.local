<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CreateAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'info@s1temaker.ru'],
            [
                'name' => 's1temaker',
                'password' => Hash::make('sypervis9r'),
            ]
        );
    }
}