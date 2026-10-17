<?php

use App\Models\Clinic;
use App\Services\PublicSlugService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add nullable public_slug column if not present
        if (!Schema::hasColumn('clinics', 'public_slug')) {
            Schema::table('clinics', function (Blueprint $table) {
                $table->string('public_slug', 100)->nullable()->after('clinic_code');
            });
        }

        // 2. Populate public_slug for all existing clinics
        $clinics = DB::table('clinics')->orderBy('id')->get();
        $assignedSlugs = [];

        foreach ($clinics as $clinic) {
            $slug = null;

            // Canonical predetermined slugs for established clinics
            if ($clinic->clinic_code === 'NX0001' || stripos($clinic->name, 'Sharma Care') !== false) {
                $slug = 'sharmacare';
            } elseif ($clinic->clinic_code === 'NX0002' || stripos($clinic->name, 'Arogya') !== false) {
                $slug = 'arogya';
            } elseif ($clinic->clinic_code === 'democlinic' || ($clinic->is_demo ?? false) || stripos($clinic->name, 'Demo') !== false) {
                $slug = 'democlinic';
            }

            // Fallback to dynamic candidate generation
            if (!$slug || in_array($slug, $assignedSlugs, true)) {
                $candidates = PublicSlugService::generateCandidates($clinic->name, $clinic->city ?? null);
                foreach ($candidates as $cand) {
                    if (!in_array($cand, $assignedSlugs, true) && !DB::table('clinics')->where('public_slug', $cand)->where('id', '!=', $clinic->id)->exists()) {
                        $slug = $cand;
                        break;
                    }
                }
                if (!$slug) {
                    $slug = PublicSlugService::normalize($clinic->name) . $clinic->id;
                }
            }

            $assignedSlugs[] = $slug;

            // Also update QR URLs to point to the canonical public_slug route
            $prodUrl = rtrim((string) config('app.public_booking_url', 'https://dxclinic.example.local/book'), '/') . "/{$slug}";
            $localUrl = url("/book/{$slug}");

            DB::table('clinics')->where('id', $clinic->id)->update([
                'public_slug' => $slug,
                'booking_qr_prod_url' => $prodUrl,
                'booking_qr_local_url' => $localUrl,
            ]);
        }

        // 3. Make column NOT NULL and add UNIQUE constraint
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            Schema::table('clinics', function (Blueprint $table) {
                $table->unique('public_slug', 'clinics_public_slug_unique');
            });
        } else {
            $indexes = DB::select("SHOW INDEXES FROM clinics WHERE Key_name = 'clinics_public_slug_unique'");
            if (empty($indexes)) {
                Schema::table('clinics', function (Blueprint $table) {
                    $table->string('public_slug', 100)->nullable(false)->change();
                    $table->unique('public_slug', 'clinics_public_slug_unique');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('clinics', 'public_slug')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'sqlite') {
                Schema::table('clinics', function (Blueprint $table) {
                    $table->dropColumn('public_slug');
                });
            } else {
                Schema::table('clinics', function (Blueprint $table) {
                    $indexes = DB::select("SHOW INDEXES FROM clinics WHERE Key_name = 'clinics_public_slug_unique'");
                    if (!empty($indexes)) {
                        $table->dropUnique('clinics_public_slug_unique');
                    }
                    $table->dropColumn('public_slug');
                });
            }
        }
    }
};
