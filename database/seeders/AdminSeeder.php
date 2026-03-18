<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::create([
            'name' => 'JetPicker Admin',
            'email' => 'admin@jetpicker.com',
            'password' => Hash::make('JetPicker@dmin2026!'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }
}