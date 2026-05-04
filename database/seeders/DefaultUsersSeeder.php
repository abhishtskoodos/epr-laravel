<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultUsersSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@epr.test'],
            [
                'name' => 'EPR Admin',
                'phone' => '9999999999',
                'password' => 'password', // hashed via cast
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
        );
        $admin->syncRoles(['admin']);

        $finance = User::updateOrCreate(
            ['email' => 'finance@epr.test'],
            [
                'name' => 'Finance Officer',
                'phone' => '9999999998',
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $finance->syncRoles(['finance']);

        $inspector = User::updateOrCreate(
            ['email' => 'inspector@epr.test'],
            [
                'name' => 'Center Inspector',
                'phone' => '9999999997',
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $inspector->syncRoles(['inspector']);
    }
}
