<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $employee = Employee::updateOrCreate(
            ['contact_email' => 'admin@naac.local'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'contact_number' => 917000001,
                'address' => 'Naga Alta Agri Corp HQ',
                'profile_picture' => null,
            ]
        );

        $attributes = [
            'employee_id' => $employee->id,
            'email' => 'admin@naac.local',
            'password' => Hash::make('Admin@123'),
            'role' => 'admin',
            'is_active' => true,
            'is_online' => false,
            'user_name' => 'admin',
        ];

        if (Schema::hasColumn('users', 'name')) {
            $attributes['name'] = 'Admin User';
        }

        User::updateOrCreate(
            ['email' => 'admin@naac.local'],
            $attributes
        );
    }
}
