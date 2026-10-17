<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\FeeMaster;
use Illuminate\Database\Seeder;

class FeeMasterSeeder extends Seeder
{
    public function run(): void
    {
        $clinicId = Clinic::first()?->id ?? 1;

        FeeMaster::updateOrCreate(
            ['clinic_id' => $clinicId, 'code' => 'CONSULTATION_PHYSICAL'],
            [
                'name' => 'Clinic Consultancy',
                'amount' => 600.00,
                'is_active' => true,
            ]
        );

        FeeMaster::updateOrCreate(
            ['clinic_id' => $clinicId, 'code' => 'CONSULTATION_ONLINE'],
            [
                'name' => 'Online Consultancy',
                'amount' => 700.00,
                'is_active' => true,
            ]
        );
    }
}
