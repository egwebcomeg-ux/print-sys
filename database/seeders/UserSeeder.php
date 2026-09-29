<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One account per role. Password is `password` — change it after the first
 * login on any shared or production environment.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'مدير النظام', 'email' => 'admin@pantopack.local', 'role' => UserRole::Admin],
            ['name' => 'موظف المبيعات', 'email' => 'sales@pantopack.local', 'role' => UserRole::Sales],
            ['name' => 'مسؤول الإنتاج', 'email' => 'production@pantopack.local', 'role' => UserRole::Production],
        ];

        foreach ($accounts as $account) {
            User::query()->firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
