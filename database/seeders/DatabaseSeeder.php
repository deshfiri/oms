<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $defaults = [
            ['Admin',           'admin@admin.com',          User::ROLE_ADMIN,      'admin123'],
            ['Pia Picker',      'picker@dfoms.test',        User::ROLE_PICKER,     'password'],
            ['Paul Packer',     'packer@dfoms.test',        User::ROLE_PACKER,     'password'],
            ['Dipa Dispatcher', 'dispatcher@dfoms.test',    User::ROLE_DISPATCHER, 'password'],
            ['Reema Returns',   'returns@dfoms.test',       User::ROLE_RETURNS,    'password'],
            ['Dilip Damage',    'damage@dfoms.test',        User::ROLE_DAMAGE,     'password'],
            ['Shawon Stock',    'stock@dfoms.test',         User::ROLE_STOCK,      'password'],
        ];

        foreach ($defaults as [$name, $email, $role, $password]) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'role' => $role, 'is_active' => true],
            );
        }
    }
}
