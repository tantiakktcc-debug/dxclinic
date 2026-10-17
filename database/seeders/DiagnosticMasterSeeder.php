<?php

namespace Database\Seeders;

use App\Models\DiagnosticItem;
use Illuminate\Database\Seeder;

class DiagnosticMasterSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Blood Tests
        $testsCsvPath = base_path('common_blood_tests.csv');
        if (file_exists($testsCsvPath)) {
            $handle = fopen($testsCsvPath, 'r');
            if ($handle !== false) {
                $header = fgetcsv($handle);
                $index = 1;
                while (($row = fgetcsv($handle)) !== false) {
                    $testName = trim($row[0] ?? '');
                    if ($testName === '') continue;

                    $rawRate = trim($row[1] ?? '');
                    $rate = is_numeric($rawRate) && (float) $rawRate > 0 ? (float) $rawRate : 250.00;
                    $code = 'TEST-' . str_pad((string) $index++, 4, '0', STR_PAD_LEFT);

                    DiagnosticItem::firstOrCreate(
                        ['name' => $testName, 'type' => 'TEST'],
                        [
                            'code' => $code,
                            'rate' => $rate,
                            'description' => "Standard laboratory test: {$testName}",
                            'is_active' => true,
                            'usage_count' => 0,
                        ]
                    );
                }
                fclose($handle);
            }
        }

        // 2. Seed Test Packages
        $packagesCsvPath = base_path('common_test_packages.csv');
        if (file_exists($packagesCsvPath)) {
            $handle = fopen($packagesCsvPath, 'r');
            if ($handle !== false) {
                $header = fgetcsv($handle);
                while (($row = fgetcsv($handle)) !== false) {
                    $packageName = trim($row[0] ?? '');
                    $packageCode = trim($row[1] ?? '');
                    $description = trim($row[2] ?? '');

                    if ($packageName === '') continue;

                    DiagnosticItem::firstOrCreate(
                        ['code' => $packageCode, 'type' => 'PACKAGE'],
                        [
                            'name' => $packageName,
                            'description' => $description,
                            'rate' => 1200.00, // Standard panel default rate
                            'is_active' => true,
                            'usage_count' => 0,
                        ]
                    );
                }
                fclose($handle);
            }
        }
    }
}
