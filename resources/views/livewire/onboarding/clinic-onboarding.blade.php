<div class="max-w-5xl mx-auto space-y-6">
    <!-- Onboarding Header & Stepper -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-slate-100">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight">Clinic Onboarding Wizard</h1>
                <p class="text-xs text-slate-500 mt-0.5">Set up your clinic profile, doctor identity, banking relations, and opening balances.</p>
            </div>
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                    Step {{ $currentStep }} of 5
                </span>
            </div>
        </div>

        <!-- 5-Step Visual Stepper -->
        <div class="grid grid-cols-5 gap-2 pt-5">
            @php
                $stepList = [
                    1 => 'Clinic Details',
                    2 => 'Doctor Details',
                    3 => 'Banking Details',
                    4 => 'Opening Balances',
                    5 => 'Review & Complete',
                ];
            @endphp

            @foreach ($stepList as $stepIndex => $stepTitle)
                <div class="flex flex-col items-center text-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all {{ $currentStep > $stepIndex ? 'bg-emerald-600 text-white' : ($currentStep === $stepIndex ? 'bg-blue-600 text-white ring-4 ring-blue-100' : 'bg-slate-100 text-slate-400') }}">
                        @if ($currentStep > $stepIndex)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        @else
                            {{ $stepIndex }}
                        @endif
                    </div>
                    <span class="text-[11px] font-bold mt-2 {{ $currentStep === $stepIndex ? 'text-blue-700' : 'text-slate-500' }}">
                        {{ $stepTitle }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Step Container -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        
        <!-- STEP 1: CLINIC DETAILS -->
        @if ($currentStep === 1)
            <div class="p-6 sm:p-8 space-y-6">
                <div>
                    <h2 class="text-lg font-black text-slate-900">1. Clinic Details</h2>
                    <p class="text-xs text-slate-500">Enter your clinic basic information</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                    <!-- Form Fields -->
                    <div class="lg:col-span-2 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Clinic Name *</label>
                            <input type="text" wire:model="clinic_name" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            @error('clinic_name') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Registration Number</label>
                            <input type="text" wire:model="clinic_registration_number" placeholder="CLN123456" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            @error('clinic_registration_number') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Address *</label>
                            <textarea wire:model="clinic_address" rows="2" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="123, MG Road"></textarea>
                            @error('clinic_address') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">City *</label>
                                <input type="text" wire:model="clinic_city" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                @error('clinic_city') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Phone *</label>
                                <input type="text" wire:model="clinic_phone" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                @error('clinic_phone') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Email</label>
                            <input type="email" wire:model="clinic_email" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            @error('clinic_email') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Right Side Clinic Visual Card -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 text-center flex flex-col items-center justify-center">
                        <div class="w-24 h-24 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center mb-3">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                        <h4 class="font-bold text-slate-800 text-sm">{{ $clinic_name ?: 'Your Clinic' }}</h4>
                        <p class="text-xs text-slate-400 mt-1">{{ $clinic_address ?: 'Clinic Address' }}, {{ $clinic_city ?: 'City' }}</p>
                        <span class="mt-3 text-[11px] font-semibold text-blue-600 bg-blue-50 border border-blue-200 px-2.5 py-1 rounded-full">
                            Single Doctor Facility
                        </span>
                    </div>
                </div>
            </div>
        @endif

        <!-- STEP 2: DOCTOR DETAILS -->
        @if ($currentStep === 2)
            <div class="p-6 sm:p-8 space-y-6">
                <div>
                    <h2 class="text-lg font-black text-slate-900">2. Doctor Details</h2>
                    <p class="text-xs text-slate-500">Add operational doctor information</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                    <!-- Form Fields -->
                    <div class="lg:col-span-2 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Doctor Name *</label>
                            <input type="text" wire:model="doctor_name" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            @error('doctor_name') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Qualification *</label>
                                <input type="text" wire:model="doctor_qualification" placeholder="MBBS, MD" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                @error('doctor_qualification') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Registration Number</label>
                                <input type="text" wire:model="doctor_registration_number" placeholder="MCI-12345" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                @error('doctor_registration_number') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Specialization *</label>
                            <input type="text" wire:model="doctor_specialization" placeholder="General Physician" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            @error('doctor_specialization') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">OPD Consultation Fees (₹) *</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 font-bold">&#8377;</span>
                                    <input type="number" step="10" wire:model="opd_fees" class="w-full rounded-xl border border-slate-200 pl-8 pr-3.5 py-2.5 text-sm font-bold text-slate-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                </div>
                                @error('opd_fees') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Consultation Duration (mins) *</label>
                                <input type="number" wire:model="consultation_duration" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                @error('consultation_duration') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Phone</label>
                                <input type="text" wire:model="doctor_contact_number" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Email</label>
                                <input type="email" wire:model="doctor_email" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            </div>
                        </div>
                    </div>

                    <!-- Right Doctor Profile Preview Card -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 text-center flex flex-col items-center justify-center">
                        <div class="w-24 h-24 rounded-full bg-slate-200 border-2 border-white shadow-md flex items-center justify-center text-4xl mb-3">
                            👨‍⚕️
                        </div>
                        <h4 class="font-bold text-slate-800 text-sm">{{ $doctor_name ?: 'Dr. Name' }}</h4>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $doctor_qualification }}</p>
                        <p class="text-xs font-semibold text-blue-600 mt-1">{{ $doctor_specialization }}</p>
                        <div class="mt-4 pt-3 border-t border-slate-200/80 w-full flex justify-between text-xs text-slate-600">
                            <span>Fee:</span>
                            <span class="font-bold text-slate-900">&#8377; {{ number_format($opd_fees, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- STEP 3: BANKING DETAILS -->
        @if ($currentStep === 3)
            <div class="p-6 sm:p-8 space-y-6">
                <div>
                    <h2 class="text-lg font-black text-slate-900">3. Banking Details</h2>
                    <p class="text-xs text-slate-500">Add your clinic bank account details for digital collections and UPI settlement</p>
                </div>

                <div class="space-y-4 max-w-2xl">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Bank Name *</label>
                        <input type="text" wire:model="bank_name" placeholder="State Bank of India" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        @error('bank_name') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Account Holder Name *</label>
                        <input type="text" wire:model="account_holder" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        @error('account_holder') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Account Number *</label>
                            <input type="text" wire:model="account_number" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            @error('account_number') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">IFSC Code *</label>
                            <input type="text" wire:model="ifsc" placeholder="SBIN0001234" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            @error('ifsc') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Branch Name</label>
                            <input type="text" wire:model="branch" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">UPI ID</label>
                            <input type="text" wire:model="upi_id" placeholder="clinic@sbi" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- STEP 4: OPENING BALANCES & INITIAL CASH SETUP -->
        @if ($currentStep === 4)
            <div class="p-6 sm:p-8 space-y-6">
                <div>
                    <h2 class="text-lg font-black text-slate-900">4. Opening Balances & Initial Cash Setup</h2>
                    <p class="text-xs text-slate-500">Set your current balances to initialize double-entry accounting ledgers</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Opening Bank Balance -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Opening Balance of Bank Account *
                        </label>
                        <div class="relative mt-2">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 font-bold">&#8377;</span>
                            <input type="number" step="0.01" wire:model="opening_bank_balance" class="w-full rounded-xl border border-slate-200 pl-8 pr-3.5 py-2.5 text-lg font-black text-slate-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>
                        <p class="text-[11px] text-slate-500 mt-2">Current balance as per bank statement. Booked to Bank Ledger via Opening Balance Equity.</p>
                        @error('opening_bank_balance') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- Opening Cash in Hand -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Cash in Hand *
                        </label>
                        <div class="relative mt-2">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 font-bold">&#8377;</span>
                            <input type="number" step="0.01" wire:model="opening_cash_in_hand" class="w-full rounded-xl border border-slate-200 pl-8 pr-3.5 py-2.5 text-lg font-black text-slate-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>
                        <p class="text-[11px] text-slate-500 mt-2">Current physical cash available in clinic before operations begin.</p>
                        @error('opening_cash_in_hand') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Initial Day Collections & Expenses (Today) Accordion -->
                <div class="border border-slate-200 rounded-2xl overflow-hidden" x-data="{ open: true }">
                    <button type="button" @click="open = !open" class="w-full px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between text-left font-bold text-xs text-slate-700 uppercase tracking-wider">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            <span>Initial Day Collections & Expenses (Today)</span>
                        </div>
                        <span class="text-[11px] font-semibold text-slate-400 normal-case">System-derived from operational records</span>
                    </button>

                    <div x-show="open" class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4 bg-white">
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-[11px] font-semibold text-slate-500 block mb-1">Today UPI Collection</span>
                            <div class="font-bold text-slate-800 text-sm">&#8377; {{ number_format($today_initial_upi, 2) }}</div>
                            <span class="text-[10px] text-slate-400 mt-1 block">Transaction-derived</span>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-[11px] font-semibold text-slate-500 block mb-1">Today Expenses</span>
                            <div class="font-bold text-slate-800 text-sm">&#8377; {{ number_format($today_initial_expenses, 2) }}</div>
                            <span class="text-[10px] text-slate-400 mt-1 block">From expense vouchers</span>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-[11px] font-semibold text-slate-500 block mb-1">Today Bank Cash Deposit Amount</span>
                            <div class="font-bold text-slate-800 text-sm">&#8377; {{ number_format($today_initial_deposit, 2) }}</div>
                            <span class="text-[10px] text-slate-400 mt-1 block">From fund contra slips</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- STEP 5: REVIEW & COMPLETE -->
        @if ($currentStep === 5)
            <div class="p-6 sm:p-8 space-y-6">
                <div>
                    <h2 class="text-lg font-black text-slate-900">5. Review & Complete</h2>
                    <p class="text-xs text-slate-500">Please review your details before completing setup</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <!-- Clinic Card -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2">
                        <div class="flex items-center gap-2 text-blue-600 font-bold text-xs uppercase tracking-wider">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            <span>Clinic Details</span>
                        </div>
                        <div class="font-bold text-sm text-slate-900">{{ $clinic_name }}</div>
                        <div class="text-xs text-slate-500 leading-relaxed">
                            {{ $clinic_address }}, {{ $clinic_city }}<br>
                            Phone: {{ $clinic_phone }}<br>
                            Email: {{ $clinic_email ?: '—' }}
                        </div>
                    </div>

                    <!-- Doctor Card -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2">
                        <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-wider">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            <span>Doctor Details</span>
                        </div>
                        <div class="font-bold text-sm text-slate-900">{{ $doctor_name }}</div>
                        <div class="text-xs text-slate-500 leading-relaxed">
                            {{ $doctor_qualification }}<br>
                            {{ $doctor_specialization }}<br>
                            OPD Fees: <strong class="text-slate-800">&#8377; {{ number_format($opd_fees, 2) }}</strong>
                        </div>
                    </div>

                    <!-- Banking Card -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2">
                        <div class="flex items-center gap-2 text-emerald-600 font-bold text-xs uppercase tracking-wider">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                            <span>Banking Details</span>
                        </div>
                        <div class="font-bold text-sm text-slate-900">{{ $bank_name }}</div>
                        <div class="text-xs text-slate-500 leading-relaxed">
                            A/c No: {{ $account_number }}<br>
                            IFSC: {{ $ifsc }}<br>
                            Branch: {{ $branch ?: '—' }}
                        </div>
                    </div>

                    <!-- Opening Balances Card -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2">
                        <div class="flex items-center gap-2 text-amber-600 font-bold text-xs uppercase tracking-wider">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Opening Balances</span>
                        </div>
                        <div class="text-xs text-slate-500 space-y-1">
                            <div class="flex justify-between">
                                <span>Bank Balance:</span>
                                <strong class="text-slate-900">&#8377; {{ number_format($opening_bank_balance, 2) }}</strong>
                            </div>
                            <div class="flex justify-between">
                                <span>Cash in Hand:</span>
                                <strong class="text-slate-900">&#8377; {{ number_format($opening_cash_in_hand, 2) }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Today Initial Card -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2 sm:col-span-2 lg:col-span-2">
                        <div class="flex items-center gap-2 text-purple-600 font-bold text-xs uppercase tracking-wider">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            <span>Today Initial Transactions</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-xs text-slate-500 pt-1">
                            <div>UPI: <strong class="text-slate-900">&#8377; 0.00</strong></div>
                            <div>Expenses: <strong class="text-slate-900">&#8377; 0.00</strong></div>
                            <div>Deposit: <strong class="text-slate-900">&#8377; 0.00</strong></div>
                        </div>
                    </div>
                </div>

                <!-- Confirmation Checkbox -->
                <div class="pt-4 border-t border-slate-200">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="is_confirmed" id="chk_onboarding_confirmed" class="w-5 h-5 rounded text-blue-600 border-slate-300 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-slate-800">I confirm the above information is correct and ready to initialize clinic books.</span>
                    </label>
                    @error('is_confirmed') <span class="text-xs text-rose-600 block mt-1">{{ $message }}</span> @enderror
                </div>
            </div>
        @endif

        <!-- Bottom Navigation Buttons -->
        <div class="px-6 sm:px-8 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
            @if ($currentStep > 1)
                <button type="button" wire:click="prevStep" id="btn_onboarding_prev" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-white text-sm font-bold transition">
                    &larr; Back
                </button>
            @else
                <div></div>
            @endif

            @if ($currentStep < 5)
                <button type="button" wire:click="nextStep" id="btn_onboarding_next" class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold transition shadow-sm flex items-center gap-1.5">
                    <span>Next</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                </button>
            @else
                <button type="button" wire:click="completeOnboarding" id="btn_complete_onboarding" class="px-7 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition shadow-md flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    <span>Complete Setup</span>
                </button>
            @endif
        </div>
    </div>
</div>
