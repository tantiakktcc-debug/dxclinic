<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;

class SystemLedgerSeeder extends Seeder
{
    public function run(): void
    {
        $ledgers = [
            ['code' => '1001', 'name' => 'Cash in Hand', 'type' => 'ASSET', 'subtype' => 'CURRENT_ASSET', 'description' => 'Primary cash in hand physical register', 'is_system' => true],
            ['code' => '3001', 'name' => 'Opening Balance Equity', 'type' => 'EQUITY', 'subtype' => 'EQUITY', 'description' => 'System equity account for opening balance offset', 'is_system' => true],
            ['code' => '4001', 'name' => 'Consultation Income', 'type' => 'INCOME', 'subtype' => 'OPERATING_REVENUE', 'description' => 'Revenue from patient doctor consultations', 'is_system' => true],
            ['code' => '4002', 'name' => 'Diagnostic Income', 'type' => 'INCOME', 'subtype' => 'OPERATING_REVENUE', 'description' => 'Revenue from lab tests and packages', 'is_system' => true],
            ['code' => '5001', 'name' => 'General Operating Expenses', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Clinic general day-to-day operating expenses', 'is_system' => true],
            ['code' => '5002', 'name' => 'Medical Supplies & Consumables', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Medical consumables, gloves, syringes, reagents', 'is_system' => true],
            ['code' => '5003', 'name' => 'Utilities & Electricity', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Electricity, water, internet, cleaning', 'is_system' => true],
            ['code' => '5004', 'name' => 'Staff & Administrative', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Staff tea, refreshments, and conveyance', 'is_system' => true],
            ['code' => '5005', 'name' => 'Printing & Stationery', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Prescription pads, paper, printing toner', 'is_system' => true],
            ['code' => '5013', 'name' => 'Other Miscellaneous Expenses', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Sundry operating expenses', 'is_system' => true],
        ];

        foreach ($ledgers as $l) {
            LedgerAccount::firstOrCreate(['code' => $l['code']], $l);
        }

        // Seed default expense categories
        $categories = [
            ['code' => 'EXP-GEN', 'name' => 'General Operating Expenses', 'ledger_code' => '5001'],
            ['code' => 'EXP-MED', 'name' => 'Medical Supplies & Consumables', 'ledger_code' => '5002'],
            ['code' => 'EXP-UTL', 'name' => 'Utilities & Maintenance', 'ledger_code' => '5003'],
            ['code' => 'EXP-STF', 'name' => 'Staff & Refreshment', 'ledger_code' => '5004'],
            ['code' => 'EXP-PRN', 'name' => 'Printing & Stationery', 'ledger_code' => '5005'],
            ['code' => 'EXP-TEA', 'name' => 'Tea/Coffee', 'ledger_code' => '5013'],
        ];

        foreach ($categories as $c) {
            $ledger = LedgerAccount::where('code', $c['ledger_code'])->first();
            if ($ledger) {
                ExpenseCategory::firstOrCreate(
                    ['code' => $c['code']],
                    ['name' => $c['name'], 'ledger_account_id' => $ledger->id]
                );
            }
        }
    }
}
