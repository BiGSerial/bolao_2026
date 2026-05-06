<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrNew([
            'email' => env('DEFAULT_ADMIN_EMAIL', 'admin@bolao.local'),
        ]);

        $user->forceFill([
            'name' => env('DEFAULT_ADMIN_NAME', 'Administrador'),
            'area' => 'Administracao',
            'phone' => env('DEFAULT_ADMIN_PHONE', '5500000000000'),
            'status' => 'active',
            'is_admin' => true,
            'must_change_password' => true,
            'password_changed_at' => null,
            'password' => Hash::make(env('DEFAULT_ADMIN_PASSWORD', 'ChangeMe!123')),
        ])->save();
    }
}
