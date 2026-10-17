<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $ledgers = [
            ['code' => '5006', 'name' => 'Rent Expense', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Clinic premises rent', 'is_system' => true],
            ['code' => '5007', 'name' => 'Electricity & Power', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Electricity and power bills', 'is_system' => true],
            ['code' => '5008', 'name' => 'Internet & Communications', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Broadband and clinic internet', 'is_system' => true],
            ['code' => '5009', 'name' => 'Telephone & Mobile', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Clinic telephone and mobile lines', 'is_system' => true],
            ['code' => '5010', 'name' => 'Staff Salaries & Wages', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Staff salary and wages', 'is_system' => true],
            ['code' => '5011', 'name' => 'Bank Charges & Fees', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Bank charges, soundbox, transaction fees', 'is_system' => true],
            ['code' => '5012', 'name' => 'Cleaning & Sanitation', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Clinic housekeeping and sanitation supplies', 'is_system' => true],
            ['code' => '5013', 'name' => 'Other Miscellaneous Expenses', 'type' => 'EXPENSE', 'subtype' => 'OPERATING_EXPENSE', 'description' => 'Sundry operating expenses', 'is_system' => true],
        ];

        foreach ($ledgers as $l) {
            LedgerAccount::firstOrCreate(['code' => $l['code']], $l);
        }

        $categories = [
            ['code' => 'EXP-RNT', 'name' => 'Rent', 'ledger_code' => '5006', 'display_order' => 1, 'description' => 'Premises rent'],
            ['code' => 'EXP-ELE', 'name' => 'Electricity', 'ledger_code' => '5007', 'display_order' => 2, 'description' => 'Power and electricity utility'],
            ['code' => 'EXP-INT', 'name' => 'Internet', 'ledger_code' => '5008', 'display_order' => 3, 'description' => 'Clinic broadband and Wi-Fi'],
            ['code' => 'EXP-TEL', 'name' => 'Telephone', 'ledger_code' => '5009', 'display_order' => 4, 'description' => 'Telephone lines and mobile recharges'],
            ['code' => 'EXP-PRN', 'name' => 'Printing/Stationery', 'ledger_code' => '5005', 'display_order' => 5, 'description' => 'Prescription pads, paper, printing'],
            ['code' => 'EXP-STF', 'name' => 'Salary/Staff', 'ledger_code' => '5010', 'display_order' => 6, 'description' => 'Clinic staff salaries and allowances'],
            ['code' => 'EXP-BNK', 'name' => 'Bank Charges', 'ledger_code' => '5011', 'display_order' => 7, 'description' => 'Bank service fees and charges'],
            ['code' => 'EXP-MED', 'name' => 'Clinic Supplies', 'ledger_code' => '5002', 'display_order' => 8, 'description' => 'Medical consumables and clinic supplies'],
            ['code' => 'EXP-CLN', 'name' => 'Cleaning', 'ledger_code' => '5012', 'display_order' => 9, 'description' => 'Housekeeping, disinfection and cleaning'],
            ['code' => 'EXP-OTH', 'name' => 'Other Expense', 'ledger_code' => '5013', 'display_order' => 10, 'description' => 'Miscellaneous expenses'],
            ['code' => 'EXP-TEA', 'name' => 'Tea/Coffee', 'ledger_code' => '5013', 'display_order' => 11, 'description' => 'Tea, coffee, pantry and refreshments'],
        ];

        foreach ($categories as $c) {
            $ledger = LedgerAccount::where('code', $c['ledger_code'])->first();
            if ($ledger) {
                ExpenseCategory::updateOrCreate(
                    ['code' => $c['code']],
                    [
                        'name' => $c['name'],
                        'ledger_account_id' => $ledger->id,
                        'is_active' => true,
                        'display_order' => $c['display_order'],
                        'description' => $c['description'],
                    ]
                );
            }
        }
    }
}
