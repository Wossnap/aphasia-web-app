<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $password = env('ADMIN_DEFAULT_PASSWORD');

        if (!$password) {
            $this->command->warn('ADMIN_DEFAULT_PASSWORD is not set — skipping admin user creation.');
            return;
        }

        User::create([
            'name' => 'Admin',
            'email' => env('ADMIN_DEFAULT_EMAIL', 'admin@aphasia.com'),
            'is_admin' => true,
            'password' => Hash::make($password),
        ]);
    }
}
