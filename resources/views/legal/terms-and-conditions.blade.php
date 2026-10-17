@extends('components.layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Terms & Conditions</h1>
            <p class="text-xs text-slate-500 mt-0.5">Operational terms of service, appointment booking governance, and legal boundaries.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-semibold">
            &larr; Return to Dashboard
        </a>
    </div>

    <!-- Metadata Card -->
    <div class="p-6 bg-white rounded-2xl border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-indigo-800 bg-indigo-50 px-3 py-1 rounded-full border border-indigo-200">
                    Operational Terms
                </span>
                <span class="text-xs text-slate-400 font-medium">Effective Date: {{ $effectiveDate }}</span>
            </div>
            <h2 class="text-lg font-bold text-slate-900 mt-2">DxClinic Operational Terms of Service</h2>
            <p class="text-xs text-slate-500 mt-0.5">Published by <strong>{{ $company }}</strong> &bull; Lead Architect: <strong>{{ $developerName }}</strong></p>
        </div>
        <a href="{{ $onlineTermsUrl }}" target="_blank" rel="noopener noreferrer" class="px-4 py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 rounded-xl text-xs font-bold transition flex items-center gap-1.5 self-start sm:self-center">
            <span>Read on Official Website</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
        </a>
    </div>

    <!-- Section 1: Acceptance of Terms -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-3">
        <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider">1. Acceptance & Authorization</h3>
        <p class="text-xs text-slate-600 leading-relaxed">
            By accessing, deploying, or utilizing the DxClinic application suite, you agree to comply with and be bound by these Terms & Conditions. If you are operating DxClinic on behalf of a healthcare institution or clinic, you represent that you possess the administrative authority to accept these terms on behalf of that entity.
        </p>
    </div>

    <!-- Section 2: Patient Registration & Continuous Token Allocation -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-3">
        <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider">2. Patient Registration & Queue Tokens</h3>
        <ul class="list-disc pl-5 text-xs text-slate-600 space-y-1.5 leading-relaxed">
            <li><strong>Single Daily Sequence:</strong> Tokens are numbered continuously from 001 for each operational day across morning and evening sessions without resetting.</li>
            <li><strong>Accurate Patient Demographics:</strong> Users and operators must ensure patient names, age, sex, address, city, and mobile contact are accurately recorded.</li>
            <li><strong>Idempotent Arrival Check-In:</strong> Online self-booked tokens require physical arrival check-in at the operator desk before consultation. Re-checking in an already arrived patient resolves existing visits without issuing duplicate tokens.</li>
        </ul>
    </div>

    <!-- Section 3: Clinical Disclaimers & Independent Professional Practice -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-3">
        <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider">3. Clinical Responsibility & Physician Discretion</h3>
        <p class="text-xs text-slate-600 leading-relaxed">
            DxClinic is exclusively an administrative, workflow, and financial management software platform. It does not practice medicine or make clinical diagnoses. The attending licensed physician retains exclusive, uncompromised responsibility for all medical evaluations, clinical diagnoses, drug prescriptions, and treatment regimens.
        </p>
    </div>

    <!-- Section 4: Physical UPI Soundbox & Payment Verification -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-3">
        <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider">4. Payment Processing & Anti-Fraud Soundbox Control</h3>
        <p class="text-xs text-slate-600 leading-relaxed">
            All payments made via UPI require physical confirmation through an audible Soundbox announcement before marking the bill as settled in the system. The clinic operator is strictly responsible for verifying soundbox confirmation to prevent fraudulent transactions or incorrect manual entries.
        </p>
    </div>

    <!-- Section 5: Printed Records & Pre-Printed Pad Overlays -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-3">
        <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider">5. Document Calibration & Physical Doctor Pads</h3>
        <p class="text-xs text-slate-600 leading-relaxed">
            DxClinic formats the Doctor Pad header strictly with the patient's identity, token, vitals, and clean "Address, City" styling calibrated for pre-printed clinical stationery. A printed pad or electronic preview does not constitute a valid prescription until physically signed and stamped by the consulting physician.
        </p>
    </div>

    <!-- Section 6: Intellectual Property & Developer Ownership -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-3">
        <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider">6. Intellectual Property</h3>
        <p class="text-xs text-slate-600 leading-relaxed">
            All source code, database architectures, user interface workflows, and branding for DxClinic are the intellectual property of <strong>{{ $company }}</strong> and <strong>{{ $developerName }}</strong>. Unlicensed duplication, reverse-engineering, or unauthorized redistribution is prohibited under applicable copyright and intellectual property laws.
        </p>
    </div>

    <!-- Section 7: Limitation of Liability -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-3">
        <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider">7. Limitation of Liability</h3>
        <p class="text-xs text-slate-600 leading-relaxed">
            To the maximum extent permitted by applicable law, DxClinic is provided on an "AS IS" and "AS AVAILABLE" basis without warranties of any kind. {{ $company }} shall not be liable for any indirect, incidental, or consequential damages resulting from hardware failures, network interruptions, or data entry discrepancies.
        </p>
    </div>

    <!-- Section 8: Support & Official Inquiries -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-4">
        <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider">8. Official Contact & Support</h3>
        <p class="text-xs text-slate-600">For inquiries regarding these Terms & Conditions or enterprise licensing:</p>

        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div><strong class="text-slate-900">Developer & Architect:</strong> <span class="text-slate-700">{{ $developerName }}</span></div>
            <div><strong class="text-slate-900">Brand / Organization:</strong> <span class="text-slate-700">{{ $company }}</span></div>
            <div><strong class="text-slate-900">Direct Support Phone:</strong> <a href="tel:{{ str_replace(' ', '', $phone) }}" class="text-blue-700 font-bold hover:underline">{{ $phone }}</a></div>
            <div><strong class="text-slate-900">Support Email:</strong> <a href="mailto:{{ $email }}" class="text-blue-700 font-bold hover:underline">{{ $email }}</a></div>
            <div class="sm:col-span-2"><strong class="text-slate-900">Official Portal:</strong> <a href="{{ $website }}" target="_blank" rel="noopener noreferrer" class="text-blue-700 font-bold hover:underline">{{ $website }}</a></div>
        </div>
    </div>

    <!-- Copyright Notice -->
    <div class="text-center py-4 text-xs text-slate-500">
        <p class="font-semibold">{{ $copyright }}</p>
    </div>
</div>
@endsection
