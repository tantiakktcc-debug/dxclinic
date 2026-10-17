<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SystemLedgerSeeder::class,
            DiagnosticMasterSeeder::class,
        ]);

        User::firstOrCreate(
            ['email' => 'admin@dxclinic.com'],
            [
                'name' => 'Clinic Admin',
                'password' => bcrypt('password'),
            ]
        );
    }
}
