<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Patient Management</h1>
            <p class="text-sm text-slate-500 mt-1">Search, view history, and manage patient profiles</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="bg-white border border-slate-200 rounded-xl px-4 py-2 shadow-xs">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total</span>
                <span class="text-lg font-black text-slate-900 ml-1">{{ $totalPatients }}</span>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-2 shadow-xs">
                <span class="text-xs font-bold text-blue-600 uppercase tracking-wider">New Today</span>
                <span class="text-lg font-black text-blue-700 ml-1">{{ $todayNewPatients }}</span>
            </div>
        </div>
    </div>

    @if($activeView === 'list')
        {{-- Search & Filters --}}
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs">
            <div class="flex flex-col md:flex-row gap-3">
                {{-- Search Input --}}
                <div class="flex-1 relative">
                    <svg class="w-5 h-5 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Search by name, ID, mobile, or city..."
                        class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                        id="patient_search_input">
                </div>

                {{-- Sex Filter --}}
                <select wire:model.live="filterSex"
                    class="px-3 py-2 border border-slate-200 rounded-lg text-sm text-slate-700 focus:ring-2 focus:ring-blue-500 outline-none"
                    id="filter_sex">
                    <option value="">All Genders</option>
                    <option value="MALE">Male</option>
                    <option value="FEMALE">Female</option>
                    <option value="OTHER">Other</option>
                </select>

                {{-- City Filter --}}
                <select wire:model.live="filterCity"
                    class="px-3 py-2 border border-slate-200 rounded-lg text-sm text-slate-700 focus:ring-2 focus:ring-blue-500 outline-none"
                    id="filter_city">
                    <option value="">All Cities</option>
                    @foreach($this->cities as $city)
                        <option value="{{ $city }}">{{ $city }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Patient List Table --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200" id="patient_list_table">
                    <thead class="bg-slate-50">
                        <tr>
                            <th wire:click="sortBy('patient_id')" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-blue-600 transition">
                                Patient ID
                                @if($sortField === 'patient_id')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th wire:click="sortBy('name')" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-blue-600 transition">
                                Name
                                @if($sortField === 'name')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Age/Sex</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Mobile</th>
                            <th wire:click="sortBy('city')" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-blue-600 transition">
                                City
                                @if($sortField === 'city')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th wire:click="sortBy('created_at')" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-blue-600 transition">
                                Registered
                                @if($sortField === 'created_at')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($patients as $patient)
                            <tr class="hover:bg-blue-50/40 transition" wire:key="patient-{{ $patient->id }}">
                                <td class="px-4 py-3">
                                    <span class="text-xs font-black text-blue-700 bg-blue-50 px-2 py-0.5 rounded">{{ $patient->patient_id }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm font-bold text-slate-800">{{ $patient->name }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ $patient->age }}Y / {{ $patient->sex }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 font-mono">{{ $patient->mobile }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ $patient->city }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ $patient->created_at?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button wire:click="viewHistory({{ $patient->id }})" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition" title="View History">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            History
                                        </button>
                                        <button wire:click="editProfile({{ $patient->id }})" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold text-slate-700 bg-slate-50 border border-slate-200 rounded-lg hover:bg-slate-100 transition" title="Edit Profile">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            Edit
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-400">
                                    <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    @if($search)
                                        No patients matching "{{ $search }}"
                                    @else
                                        No patients registered yet
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($patients, 'links'))
                <div class="px-4 py-3 border-t border-slate-200 bg-slate-50">
                    {{ $patients->links() }}
                </div>
            @endif
        </div>

    @elseif($activeView === 'history')
        {{-- Patient History View --}}
        <div class="space-y-6">
            {{-- Back button & Patient Info Card --}}
            <div class="flex items-center gap-4">
                <button wire:click="backToList" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition shadow-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to List
                </button>
                @if($selectedPatient)
                    <a href="{{ route('pdf.patient-history', $selectedPatient->id) }}" target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-bold text-rose-700 bg-rose-50 border border-rose-200 rounded-lg hover:bg-rose-100 transition shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Download PDF
                    </a>
                @endif
            </div>

            @if($selectedPatient)
                {{-- Patient Profile Summary Card --}}
                <div class="bg-gradient-to-r from-blue-900 to-slate-900 rounded-2xl p-6 text-white shadow-lg">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-2xl bg-blue-800/60 flex items-center justify-center text-2xl font-black text-blue-300 border border-blue-700/50">
                                {{ strtoupper(substr($selectedPatient->name, 0, 2)) }}
                            </div>
                            <div>
                                <h2 class="text-xl font-bold">{{ $selectedPatient->name }}</h2>
                                <div class="text-blue-300 text-sm font-semibold mt-0.5">{{ $selectedPatient->patient_id }}</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <div class="text-blue-400 text-xs font-bold uppercase">Age / Sex</div>
                                <div class="text-white font-bold">{{ $selectedPatient->age }}Y / {{ $selectedPatient->sex }}</div>
                            </div>
                            <div>
                                <div class="text-blue-400 text-xs font-bold uppercase">Mobile</div>
                                <div class="text-white font-bold">{{ $selectedPatient->mobile }}</div>
                            </div>
                            <div>
                                <div class="text-blue-400 text-xs font-bold uppercase">City</div>
                                <div class="text-white font-bold">{{ $selectedPatient->city }}</div>
                            </div>
                            <div>
                                <div class="text-blue-400 text-xs font-bold uppercase">Address</div>
                                <div class="text-white font-bold text-xs">{{ $selectedPatient->formatted_address }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($this->diagnosticLabReports) > 0)
                    {{-- Released Diagnostic Lab Reports (Revision 36) --}}
                    <div class="bg-white rounded-2xl border border-purple-200 p-6 shadow-xs">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="w-8 h-8 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center font-black">🧪</span>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800">Released Diagnostic Reports</h3>
                                    <p class="text-xs text-slate-500">Official clinical test results authorized and released by attending clinicians</p>
                                </div>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800">{{ count($this->diagnosticLabReports) }} Released</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider">
                                        <th class="pb-2">Report #</th>
                                        <th class="pb-2">Order #</th>
                                        <th class="pb-2">Investigations</th>
                                        <th class="pb-2">Released By</th>
                                        <th class="pb-2">Released Date</th>
                                        <th class="pb-2 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($this->diagnosticLabReports as $lr)
                                        <tr class="hover:bg-purple-50/50 transition">
                                            <td class="py-2.5 font-bold font-mono text-purple-900">{{ $lr['report_number'] }}</td>
                                            <td class="py-2.5 font-mono text-slate-600">{{ $lr['order_number'] }}</td>
                                            <td class="py-2.5 text-slate-800 font-medium">{{ $lr['tests'] }}</td>
                                            <td class="py-2.5 text-slate-600">{{ $lr['released_by'] }}</td>
                                            <td class="py-2.5 text-slate-600">{{ $lr['released_at'] }}</td>
                                            <td class="py-2.5 text-right space-x-1">
                                                <a href="{{ route('print.diagnostic-result-report', $lr['id']) }}" target="_blank" class="inline-flex items-center px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px]">
                                                    Print
                                                </a>
                                                <a href="{{ route('pdf.diagnostic-result-report', $lr['id']) }}" target="_blank" class="inline-flex items-center px-2 py-1 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-[10px] border border-rose-200">
                                                    PDF
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Visit History Timeline --}}
                <div>
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4">Visit History ({{ count($this->patientHistory) }} visits)</h3>

                    @if(empty($this->patientHistory))
                        <div class="bg-white rounded-xl border border-slate-200 p-12 text-center shadow-xs">
                            <p class="text-sm text-slate-400">No visit history available for this patient.</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($this->patientHistory as $visit)
                                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs hover:shadow-md transition">
                                    {{-- Visit Header --}}
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
                                        <div class="flex items-center gap-3">
                                            <span class="text-sm font-black text-blue-800">{{ $visit['date'] }}</span>
                                            <span class="text-xs font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">{{ $visit['session'] }}</span>
                                            <span class="text-sm font-bold text-blue-700">Token #{{ $visit['token'] }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="px-2.5 py-0.5 rounded text-[11px] font-black uppercase tracking-wider
                                                {{ $visit['status'] === 'COMPLETED' ? 'bg-emerald-100 text-emerald-700' : ($visit['status'] === 'ARRIVED' ? 'bg-blue-100 text-blue-700' : 'bg-rose-100 text-rose-700') }}">
                                                {{ $visit['status'] }}
                                            </span>
                                            <span class="text-xs text-slate-500">{{ \App\Services\DoctorNamePresenter::format($visit['doctor']) }}</span>
                                        </div>
                                    </div>

                                    <div class="mt-3 space-y-3">
                                        {{-- Vitals --}}
                                        @if($visit['vital'])
                                            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                                                @foreach(['bp' => 'BP', 'pulse' => 'Pulse', 'weight' => 'Weight', 'temp' => 'Temp', 'spo2' => 'SpO2', 'sugar' => 'Sugar'] as $key => $label)
                                                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-2 text-center">
                                                        <div class="text-[10px] font-bold text-blue-600 uppercase">{{ $label }}</div>
                                                        <div class="text-xs font-bold text-blue-900">{{ $visit['vital'][$key] }}</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Billing Info --}}
                                        <div class="flex flex-wrap gap-2">
                                            @if($visit['consultation_bill'])
                                                <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 text-xs">
                                                    <span class="font-bold text-emerald-700">Consultation:</span>
                                                    <span class="font-black text-emerald-800 ml-1">₹ {{ $visit['consultation_bill']['net'] }}</span>
                                                    <span class="text-emerald-600 ml-1">({{ $visit['consultation_bill']['payment_method'] }})</span>
                                                    @if($visit['consultation_bill']['waiver'] !== '0.00')
                                                        <span class="text-emerald-500 ml-1 italic">Waiver: ₹{{ $visit['consultation_bill']['waiver'] }}</span>
                                                    @endif
                                                </div>
                                            @endif

                                            @foreach($visit['diagnostic_bills'] as $db)
                                                <div class="bg-purple-50 border border-purple-200 rounded-lg px-3 py-2 text-xs">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <div>
                                                            <span class="font-bold text-purple-700">Diagnostic ({{ $db['bill_number'] }}):</span>
                                                            <span class="font-black text-purple-800 ml-1">₹ {{ $db['net'] }}</span>
                                                        </div>
                                                        @if(!empty($db['order_status']))
                                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase
                                                                {{ $db['order_status'] === 'COMPLETED' ? 'bg-emerald-100 text-emerald-800' : 'bg-purple-100 text-purple-800' }}">
                                                                {{ $db['order_status'] }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <span class="text-purple-600 block mt-0.5">{{ $db['items'] }}</span>
                                                    @if(!empty($db['specimen_id']))
                                                        <div class="text-[11px] text-slate-600 mt-1 flex items-center gap-1.5">
                                                            <span>Sample: <strong class="font-mono text-purple-900">{{ $db['specimen_id'] }}</strong></span>
                                                            <span class="text-[10px] px-1 rounded bg-slate-200 font-semibold">{{ $db['specimen_status'] }}</span>
                                                        </div>
                                                    @endif
                                                    @if(!empty($db['report_id']))
                                                        <div class="mt-2 flex items-center gap-2">
                                                            <span class="text-[11px] font-bold text-emerald-700">✓ Report Released: {{ $db['report_number'] }}</span>
                                                            <a href="{{ route('print.diagnostic-result-report', $db['report_id']) }}" target="_blank" class="text-[10px] font-bold text-slate-700 bg-white border border-slate-300 px-2 py-0.5 rounded hover:bg-slate-100">Print</a>
                                                            <a href="{{ route('pdf.diagnostic-result-report', $db['report_id']) }}" target="_blank" class="text-[10px] font-bold text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded hover:bg-rose-100">PDF</a>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>

                                        {{-- Print Links --}}
                                        @if($visit['consultation_bill'] || !empty($visit['diagnostic_bills']) || $visit['appointment_id'])
                                            <div class="flex flex-wrap gap-2 pt-1">
                                                @if($visit['appointment_id'])
                                                    <a href="{{ route('print.doctor.pad', $visit['appointment_id']) }}" target="_blank" class="text-[11px] font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded hover:bg-slate-200 transition">🩺 Doctor Pad</a>
                                                    <a href="{{ route('pdf.doctor-pad', $visit['appointment_id']) }}" target="_blank" class="text-[11px] font-bold text-rose-600 bg-rose-50 px-2 py-1 rounded hover:bg-rose-100 transition">📄 PDF</a>
                                                @endif
                                                @if($visit['consultation_bill'])
                                                    <a href="{{ route('print.consultation.receipt', $visit['consultation_bill']['id']) }}" target="_blank" class="text-[11px] font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded hover:bg-slate-200 transition">🧾 Receipt</a>
                                                    <a href="{{ route('pdf.consultation-receipt', $visit['consultation_bill']['id']) }}" target="_blank" class="text-[11px] font-bold text-rose-600 bg-rose-50 px-2 py-1 rounded hover:bg-rose-100 transition">📄 PDF</a>
                                                @endif
                                                @foreach($visit['diagnostic_bills'] as $db)
                                                    <a href="{{ route('print.lab.slip', $db['id']) }}" target="_blank" class="text-[11px] font-bold text-purple-700 bg-purple-50 px-2 py-1 rounded hover:bg-purple-100 transition">🧪 Lab Slip</a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

    @elseif($activeView === 'edit')
        {{-- Patient Profile Edit --}}
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <button wire:click="backToList" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition shadow-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Cancel
                </button>
                <h2 class="text-lg font-bold text-slate-900">Edit Patient Profile</h2>
                @if($selectedPatient)
                    <span class="text-xs font-black text-blue-700 bg-blue-50 px-2 py-0.5 rounded">{{ $selectedPatient->patient_id }}</span>
                @endif
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs max-w-2xl">
                <form wire:submit="saveProfile" class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Full Name *</label>
                            <input type="text" wire:model="editName" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" id="edit_patient_name">
                            @error('editName') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Age *</label>
                            <input type="number" wire:model="editAge" min="1" max="130" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" id="edit_patient_age">
                            @error('editAge') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Sex *</label>
                            <select wire:model="editSex" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" id="edit_patient_sex">
                                <option value="">Select</option>
                                <option value="MALE">Male</option>
                                <option value="FEMALE">Female</option>
                                <option value="OTHER">Other</option>
                            </select>
                            @error('editSex') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Mobile *</label>
                            <input type="tel" wire:model="editMobile" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none font-mono" id="edit_patient_mobile">
                            @error('editMobile') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Address</label>
                            <input type="text" wire:model="editAddress" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" id="edit_patient_address">
                            @error('editAddress') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">City *</label>
                            <input type="text" wire:model="editCity" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" id="edit_patient_city">
                            @error('editCity') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-3 border-t border-slate-200">
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-lg hover:bg-blue-700 transition shadow-sm" id="btn_save_patient">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Save Changes
                        </button>
                        <button type="button" wire:click="backToList" class="px-4 py-2.5 text-sm font-bold text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
