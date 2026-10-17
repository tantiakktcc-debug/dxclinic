<?php

namespace App\Livewire\Onboarding;

use App\Models\BankAccount;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\FeeMaster;
use App\Models\LedgerAccount;
use App\Services\OpeningBalanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ClinicOnboarding extends Component
{
    // Step 0: Get Started, 1: Clinic Details, 2: Doctor Details, 3: Banking Details, 4: Opening Balances, 5: Review & Complete
    public int $currentStep = 1;

    // Step 1: Clinic Details
    public string $clinic_name = '';
    public string $clinic_registration_number = '';
    public string $clinic_address = '';
    public string $clinic_city = 'Varanasi';
    public string $clinic_phone = '';
    public string $clinic_email = '';

    // Step 2: Doctor Details
    public string $doctor_name = '';
    public string $doctor_qualification = '';
    public string $doctor_registration_number = '';
    public string $doctor_specialization = '';
    public float $opd_fees = 500.00;
    public int $consultation_duration = 15;
    public string $doctor_contact_number = '';
    public string $doctor_email = '';

    // Step 3: Banking Details
    public string $bank_name = '';
    public string $account_holder = '';
    public string $account_number = '';
    public string $ifsc = '';
    public string $branch = '';
    public string $upi_id = '';

    // Step 4: Opening Balances & Initial Cash Setup
    public float $opening_bank_balance = 50000.00;
    public float $opening_cash_in_hand = 10000.00;

    // Derived Initial Day Metrics (Read-only as per contract Section 13, 39)
    public float $today_initial_upi = 0.00;
    public float $today_initial_expenses = 0.00;
    public float $today_initial_deposit = 0.00;

    // Step 5: Review & Confirmation
    public bool $is_confirmed = true;
    public bool $is_completed = false;
    public ?int $clinic_id = null;

    protected $rules = [
        1 => [
            'clinic_name' => 'required|string|min:3|max:150',
            'clinic_registration_number' => 'nullable|string|max:100',
            'clinic_address' => 'required|string|min:3',
            'clinic_city' => 'required|string|min:2|max:100',
            'clinic_phone' => 'required|string|min:10',
            'clinic_email' => 'nullable|email',
        ],
        2 => [
            'doctor_name' => 'required|string|min:3|max:150',
            'doctor_qualification' => 'required|string|min:2|max:100',
            'doctor_registration_number' => 'nullable|string|max:100',
            'doctor_specialization' => 'required|string|max:100',
            'opd_fees' => 'required|numeric|min:0',
            'consultation_duration' => 'required|integer|min:1|max:120',
            'doctor_contact_number' => 'nullable|string|min:10',
            'doctor_email' => 'nullable|email',
        ],
        3 => [
            'bank_name' => 'required|string|min:2|max:100',
            'account_holder' => 'required|string|min:2|max:150',
            'account_number' => 'required|string|min:5|max:50',
            'ifsc' => 'required|string|min:5|max:20',
            'branch' => 'nullable|string|max:100',
            'upi_id' => 'nullable|string|max:100',
        ],
        4 => [
            'opening_bank_balance' => 'required|numeric|min:0',
            'opening_cash_in_hand' => 'required|numeric|min:0',
        ],
        5 => [
            'is_confirmed' => 'accepted',
        ],
    ];

    public function mount(): void
    {
        $user = Auth::user();
        $existing = $user?->clinic ?? \App\Services\TenantContext::clinic();

        if ($existing) {
            $this->clinic_id = $existing->id;
            $this->clinic_name = $existing->name;
            $this->clinic_address = $existing->address ?? '';
            $this->clinic_city = $existing->city ?: 'Varanasi';
            $this->clinic_phone = $existing->phone ?? '';
            $this->clinic_email = (string) ($existing->email ?? '');
            $this->clinic_registration_number = $existing->registration_number ?? '';
            $this->account_holder = $existing->name;
            $this->is_completed = (bool) $existing->is_onboarded;

            $doc = Doctor::where('clinic_id', $existing->id)->first();
            if ($doc) {
                $this->doctor_name = $doc->name;
                $this->doctor_qualification = $doc->qualification ?? '';
                $this->doctor_specialization = $doc->specialization ?? '';
                $this->doctor_registration_number = $doc->registration_number ?? '';
                $this->doctor_contact_number = $doc->phone ?? '';
                $this->doctor_email = $doc->email ?? '';
            } elseif ($user && $user->role === 'DOCTOR') {
                $this->doctor_name = $user->name;
                $this->doctor_contact_number = $user->phone ?? '';
                $this->doctor_email = $user->email ?? '';
            }

            $fee = FeeMaster::where('clinic_id', $existing->id)->first();
            if ($fee) {
                $this->opd_fees = (float) $fee->amount;
            }

            $bank = BankAccount::where('clinic_id', $existing->id)->first();
            if ($bank) {
                $this->bank_name = $bank->bank_name ?? '';
                $this->account_holder = $bank->account_holder_name ?? $existing->name;
                $this->account_number = $bank->account_number ?? '';
                $this->ifsc = $bank->ifsc_code ?? '';
                $this->branch = $bank->branch_name ?? '';
                $this->upi_id = $bank->upi_id ?? '';
            }
        }
    }

    public function nextStep(): void
    {
        if (isset($this->rules[$this->currentStep])) {
            $this->validate($this->rules[$this->currentStep]);
        }
        if ($this->currentStep < 5) {
            $this->currentStep++;
        }
    }

    public function prevStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function completeOnboarding(OpeningBalanceService $openingBalanceService): void
    {
        $this->validate([
            'is_confirmed' => 'accepted',
            'opening_bank_balance' => 'required|numeric|min:0',
            'opening_cash_in_hand' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($openingBalanceService) {
            // 1. Create or update Clinic Master
            $clinic = \App\Services\TenantContext::clinic()
                ?? (Auth::check() && Auth::user()->clinic_id ? Clinic::find(Auth::user()->clinic_id) : null)
                ?? (Clinic::count() === 0 ? new Clinic() : null);

            if (!$clinic) {
                abort(403, 'Unauthorized to complete onboarding without valid tenant context.');
            }
            $clinic->fill([
                'name' => $this->clinic_name,
                'address' => $this->clinic_address,
                'city' => $this->clinic_city,
                'phone' => $this->clinic_phone,
                'email' => $this->clinic_email,
                'schedule_config' => $clinic->defaultSchedule(),
                'is_onboarded' => true,
            ]);
            $clinic->save();

            // 2. Create or update Doctor Master
            Doctor::updateOrCreate(
                ['clinic_id' => $clinic->id],
                [
                    'name' => $this->doctor_name,
                    'qualification' => $this->doctor_qualification,
                    'specialization' => $this->doctor_specialization,
                    'registration_number' => $this->doctor_registration_number,
                    'contact_number' => $this->doctor_contact_number,
                ]
            );

            // 3. Create Bank Ledger Account in Chart of Accounts
            $bankLedgerCode = '1002_' . substr(preg_replace('/[^0-9]/', '', $this->account_number), -4);
            $bankLedger = LedgerAccount::firstOrCreate(
                ['code' => $bankLedgerCode],
                [
                    'name' => "Bank Account - {$this->bank_name} ({$this->account_number})",
                    'type' => 'ASSET',
                    'subtype' => 'BANK_ACCOUNT',
                    'is_system' => true,
                    'description' => "Dedicated ledger account for {$this->bank_name}",
                ]
            );

            // 4. Create Bank Account Master
            $bankAccount = BankAccount::updateOrCreate(
                ['clinic_id' => $clinic->id, 'account_number' => $this->account_number],
                [
                    'bank_name' => $this->bank_name,
                    'account_holder' => $this->account_holder,
                    'ifsc' => $this->ifsc,
                    'branch' => $this->branch,
                    'upi_id' => $this->upi_id,
                    'ledger_account_id' => $bankLedger->id,
                    'is_active' => true,
                ]
            );

            // 5. Create default Fee Master
            FeeMaster::updateOrCreate(
                ['clinic_id' => $clinic->id, 'code' => 'CONSULTATION_REGULAR'],
                [
                    'name' => 'General Doctor Consultation',
                    'amount' => $this->opd_fees,
                    'is_active' => true,
                ]
            );

            // 6. Record Opening Financial Balances via OpeningBalanceService
            $openingBalanceService->recordOpeningBalances(
                $clinic,
                (float) $this->opening_cash_in_hand,
                [$bankAccount->id => (float) $this->opening_bank_balance],
                Carbon::now()
            );

            $this->is_completed = true;
            $this->clinic_id = $clinic->id;
        });

        session()->flash('success', 'Clinic setup complete! Financial opening balances initialized successfully.');
        $this->redirect(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.onboarding.clinic-onboarding')
            ->layout('components.layouts.app', ['title' => 'Clinic Onboarding']);
    }
}
