<?php
define('LARAVEL_START', microtime(true));
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check if there's a blade compilation/rendering error for LR-000003
$lr3 = \App\Models\LicenseRequest::where('request_number', 'LR-000003')->first();
echo "LR-000003:\n";
echo "  clinic_id: " . ($lr3->clinic_id ?? 'NULL') . "\n";
echo "  isDoctorSeatAddon: " . ($lr3->isDoctorSeatAddon() ? 'true' : 'false') . "\n";
echo "  request_type raw: " . $lr3->getAttributes()['request_type'] . "\n";
echo "  additional_doctor_count: " . $lr3->additional_doctor_count . "\n";
echo "  requested_doctor_count: " . $lr3->requested_doctor_count . "\n";
echo "  doctor_rate_snapshot: " . ($lr3->doctor_rate_snapshot ?? 'NULL') . "\n";

// What about the fulfilled_at column?
$attrs = $lr3->getAttributes();
echo "  fulfilled_at exists: " . (array_key_exists('fulfilled_at', $attrs) ? 'yes: '.$attrs['fulfilled_at'] : 'no') . "\n";

// Check if this LR is actually the seeded one or a real one
echo "  created_at: " . $lr3->created_at . "\n";
echo "  status: " . $lr3->status . "\n";
echo "  admin_notes: " . ($lr3->admin_notes ?? 'NULL') . "\n";

// Check if the blade can access $snapshot['previous_capacity'] safely
$snapshot = $lr3->snapshot_data ?? [];
echo "\n  snapshot is: " . json_encode($snapshot) . "\n";
$prevCap = $snapshot['previous_capacity'] ?? ($lr3->clinic ? $lr3->clinic->totalDoctorCapacity() : ($lr3->included_doctor_count ?? 1));
echo "  prevCap resolves to: " . $prevCap . "\n";
