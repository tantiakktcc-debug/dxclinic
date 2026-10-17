<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Clinic Administrator',
                'email' => 'admin@dxclinic.local',
                'role' => 'ADMIN',
                'password' => Hash::make('DxClinic@Admin2026'),
            ],
            [
                'name' => 'Reception Desk Operator',
                'email' => 'operator@dxclinic.local',
                'role' => 'OPERATOR',
                'password' => Hash::make('DxClinic@Op2026'),
            ],
            [
                'name' => 'Dr. Rajesh Sharma',
                'email' => 'doctor@dxclinic.local',
                'role' => 'DOCTOR',
                'password' => Hash::make('DxClinic@Doc2026'),
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'role' => $userData['role'],
                    'password' => $userData['password'],
                ]
            );
        }
    }
}
