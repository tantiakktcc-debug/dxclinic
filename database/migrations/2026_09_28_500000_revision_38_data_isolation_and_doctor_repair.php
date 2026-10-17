<?php

use App\Models\Doctor;
use App\Models\FeeMaster;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\DemoService;
use App\Services\DoctorNamePresenter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Revision 38: Data Isolation, Doctor Name Normalization & Fee Master Correction
     */
    public function up(): void
    {
        // 1. Normalize all doctor names in database
        $doctors = Doctor::all();
        foreach ($doctors as $doctor) {
            $cleaned = DoctorNamePresenter::stripTitle($doctor->getRawOriginal('name'));
            if (!empty($cleaned) && $cleaned !== $doctor->getRawOriginal('name')) {
                DB::table('doctors')->where('id', $doctor->id)->update(['name' => $cleaned]);
            }
        }

        // 2. Normalize doctor users names
        $doctorUsers = User::where('role', 'DOCTOR')->get();
        foreach ($doctorUsers as $u) {
            $cleaned = DoctorNamePresenter::stripTitle($u->name);
            if (!empty($cleaned) && $cleaned !== $u->name) {
                DB::table('users')->where('id', $u->id)->update(['name' => $cleaned]);
            }
        }

        // 3. Update Fee Master terminology to 'Clinic Consultancy'
        DB::table('fee_masters')
            ->where('code', 'CONSULTATION_PHYSICAL')
            ->update(['name' => 'Clinic Consultancy']);

        DB::table('fee_masters')
            ->where('code', 'CONSULTATION_ONLINE')
            ->update(['name' => 'Online Consultancy']);

        DB::table('fee_masters')
            ->where('code', 'CONSULTATION_VISITING')
            ->update(['name' => 'Visiting Consultancy']);

        DB::table('fee_masters')
            ->where('code', 'CONSULTATION_REGULAR')
            ->update(['name' => 'Clinic Consultancy']);

        // 4. Ensure 5-Year subscription plan has duration_months = 60
        DB::table('subscription_plans')
            ->where('code', 'FIVE_YEAR')
            ->update(['duration_months' => 60]);
    }

    public function down(): void
    {
        // Reversible terminology
        DB::table('fee_masters')
            ->where('code', 'CONSULTATION_PHYSICAL')
            ->update(['name' => 'Clinic Consultation']);
    }
};
