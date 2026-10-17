@extends('components.layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Privacy Policy</h1>
            <p class="text-xs text-slate-500 mt-0.5">Authoritative data governance, privacy disclosures, and zero-telemetry commitments.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-semibold">
            &larr; Return to Dashboard
        </a>
    </div>

    <!-- Metadata Card -->
    <div class="p-6 bg-white rounded-2xl border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-emerald-800 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">
                    Active & Enforced
                </span>
                <span class="text-xs text-slate-400 font-medium">Effective Date: {{ $effectiveDate }}</span>
            </div>
            <h2 class="text-lg font-bold text-slate-900 mt-2">DxClinic Clinical Privacy Framework</h2>
            <p class="text-xs text-slate-500 mt-0.5">Governed by <strong>{{ $company }}</strong> &bull; Lead Architect: <strong>{{ $developerName }}</strong></p>
        </div>
        <a href="{{ $onlinePolicyUrl }}" target="_blank" rel="noopener noreferrer" class="px-4 py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 rounded-xl text-xs font-bold transition flex items-center gap-1.5 self-start sm:self-center">
            <span>Read on Official Website</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
        </a>
    </div>

    <!-- Section 1: Overview & Scope -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-3">
        <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider">1. Architectural Scope & Privacy Commitment</h3>
        <p class="text-xs text-slate-600 leading-relaxed">
            DxClinic is an on-premise and private-cloud clinical operations platform engineered by <strong>{{ $company }}</strong>. We hold that patient health records and clinic financial transactions are privileged, confidential materials. This application operates with a strict <strong>local-first, zero-telemetry architecture</strong> designed to ensure that clinical records remain entirely under clinic sovereignty.
        </p>
    </div>

    <!-- Section 2: Patient Health & Operational Information Collected -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-4">
        <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider">2. Information Collected & Processed</h3>
        <p class="text-xs text-slate-600">To facilitate clinical consultations, token queuing, and diagnostic investigations, DxClinic collects and manages the following data categories:</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                <h4 class="font-bold text-slate-900">A. Patient Demographics & Identity</h4>
                <p class="text-slate-600 leading-normal">Deterministic Patient ID (e.g., DXP-00001), full legal name, age, biological sex, residential address, city, and primary mobile contact number.</p>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                <h4 class="font-bold text-slate-900">B. Appointments & Continuous Token Queue</h4>
                <p class="text-slate-600 leading-normal">Appointment date, session type (Morning / Evening / Sunday Single), booking origin (Walk-in, Online PWA, Telephonic), assigned token sequence, and secure verification tokens.</p>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                <h4 class="font-bold text-slate-900">C. Clinical Vitals & Visit Encounters</h4>
                <p class="text-slate-600 leading-normal">Systolic and diastolic blood pressure, pulse rate, body temperature, body weight, oxygen saturation (SpO₂), respiratory rate, and attending operator notes.</p>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                <h4 class="font-bold text-slate-900">D. Consultation & Diagnostic Billing</h4>
                <p class="text-slate-600 leading-normal">Standard consultation fees, laboratory investigation requests, snapshotted test rates, cash counter sessions, and double-entry general ledger journals.</p>
            </div>
        </div>
    </div>

    <!-- Section 3: AI Chatbot Operational Boundary & Medical Disclaimer -->
    <div class="bg-amber-50 rounded-2xl p-6 border border-amber-200 shadow-xs space-y-3">
        <div class="flex items-center gap-2 text-amber-800 font-bold text-sm">
            <svg class="w-5 h-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <h3 class="uppercase tracking-wider">3. AI Reception Chatbot — Strictly Operational, No Medical Advice</h3>
        </div>
        <p class="text-xs text-amber-900 leading-relaxed font-medium">
            The floating AI Reception Chatbot provided within the public Patient Booking PWA is engineered exclusively for <strong>operational clinic assistance</strong>, including inquiring about doctor consultation hours, clinic address directions, and facilitating appointment slot booking.
        </p>
        <p class="text-xs text-amber-900 leading-relaxed font-bold">
            The Chatbot does NOT provide medical advice, diagnosis, triage, or pharmaceutical recommendations under any circumstances. Patients experiencing acute medical symptoms or emergencies must immediately consult a licensed physician or visit an emergency medical facility.
        </p>
    </div>

    <!-- Section 4: Manual UPI Payments & Physical Soundbox Verification -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-3">
        <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider">4. Payment Handling & Soundbox Confirmation</h3>
        <p class="text-xs text-slate-600 leading-relaxed">
            DxClinic utilizes a <strong>direct physical settlement workflow</strong>. To prevent third-party transaction fees and privacy exfiltration:
        </p>
        <ul class="list-disc pl-5 text-xs text-slate-600 space-y-1.5 leading-relaxed">
            <li><strong>No Embedded Payment Gateways:</strong> DxClinic does not integrate external payment gateway SDKs or collect debit/credit card credentials.</li>
            <li><strong>Manual Soundbox Verification:</strong> All UPI transactions are processed through the clinic's dedicated static UPI QR code and verified by physical audible Soundbox announcement before the operator confirms settlement.</li>
            <li><strong>Audited Cash Ledger:</strong> Physical cash collections are mathematically tracked from session opening through expenses, bank deposits, and closing reconciliation.</li>
        </ul>
    </div>

    <!-- Section 5: Zero Telemetry & Third-Party Non-Disclosure -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-3">
        <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider">5. Zero Third-Party Analytics, Advertising, or Sale of Data</h3>
        <p class="text-xs text-slate-600 leading-relaxed">
            In accordance with the architectural standards set by {{ $company }}:
        </p>
        <ul class="list-disc pl-5 text-xs text-slate-600 space-y-1 leading-relaxed">
            <li><strong>Zero Tracking SDKs:</strong> No Google Analytics, Firebase Analytics, Meta Pixel, or behavioral tracking libraries are installed.</li>
            <li><strong>Zero Advertising:</strong> DxClinic contains no third-party advertisements or ad networks.</li>
            <li><strong>Never Sold:</strong> Patient identifiable records and clinic financials are never sold, rented, or monetized.</li>
        </ul>
    </div>

    <!-- Section 6: Official Support & Inquiries -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-4">
        <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider">6. Contact & Privacy Inquiries</h3>
        <p class="text-xs text-slate-600">For privacy questions, record deletion requests, or technical support, please contact the developer and company directly:</p>

        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div><strong class="text-slate-900">Developer & Architect:</strong> <span class="text-slate-700">{{ $developerName }}</span></div>
            <div><strong class="text-slate-900">Brand / Organization:</strong> <span class="text-slate-700">{{ $company }}</span></div>
            <div><strong class="text-slate-900">Direct Support Phone:</strong> <a href="tel:{{ str_replace(' ', '', $phone) }}" class="text-blue-700 font-bold hover:underline">{{ $phone }}</a></div>
            <div><strong class="text-slate-900">Support Email:</strong> <a href="mailto:{{ $email }}" class="text-blue-700 font-bold hover:underline">{{ $email }}</a></div>
            <div class="sm:col-span-2"><strong class="text-slate-900">Corporate Portal:</strong> <a href="{{ $website }}" target="_blank" rel="noopener noreferrer" class="text-blue-700 font-bold hover:underline">{{ $website }}</a></div>
        </div>
    </div>

    <!-- Copyright Notice -->
    <div class="text-center py-4 text-xs text-slate-500">
        <p class="font-semibold">{{ $copyright }}</p>
    </div>
</div>
@endsection
