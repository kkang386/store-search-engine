<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => 'Search Admin',
                'password' => env('ADMIN_PASSWORD', 'secret'),
            ]
        );

        $admin->assignRole('system_admin');
    }
}
