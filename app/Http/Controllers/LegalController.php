<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class LegalController extends Controller
{
    /**
     * Verified Developer & About Section (Section 6, 7, 8, 55)
     */
    public function about(): View
    {
        return view('legal.about', [
            'developerName' => config('dxclinic.developer_name', 'Kundan Kumar'),
            'developerRole' => 'Developer & System Architect',
            'company' => config('dxclinic.developer_company', 'Nexora Solution'),
            'phone' => config('dxclinic.developer_phone') ?: '+91 98765 43210',
            'email' => config('dxclinic.developer_email', 'contact@nexorasolutionsind.co.in'),
            'website' => config('dxclinic.developer_website', 'https://nexorasolutionsind.co.in/'),
            'privacyUrl' => 'https://nexorasolutionsind.co.in/privacy-policy.php',
            'termsUrl' => 'https://nexorasolutionsind.co.in/terms-and-conditions.php',
            'copyright' => '© 2026 Nexora Solution. Licensed under GNU AGPL-3.0.',
        ]);
    }

    /**
     * Operational Privacy Policy (Section 9, 11, 56)
     */
    public function privacyPolicy(): View
    {
        return view('legal.privacy-policy', [
            'developerName' => config('dxclinic.developer_name', 'Kundan Kumar'),
            'company' => config('dxclinic.developer_company', 'Nexora Solution'),
            'phone' => config('dxclinic.developer_phone') ?: '+91 98765 43210',
            'email' => config('dxclinic.developer_email', 'contact@nexorasolutionsind.co.in'),
            'website' => config('dxclinic.developer_website', 'https://nexorasolutionsind.co.in/'),
            'onlinePolicyUrl' => 'https://nexorasolutionsind.co.in/privacy-policy.php',
            'copyright' => '© 2026 Nexora Solution. Licensed under GNU AGPL-3.0.',
            'effectiveDate' => 'September 24, 2026',
        ]);
    }

    /**
     * Operational Terms & Conditions (Section 10, 11, 56)
     */
    public function termsConditions(): View
    {
        return view('legal.terms-and-conditions', [
            'developerName' => config('dxclinic.developer_name', 'Kundan Kumar'),
            'company' => config('dxclinic.developer_company', 'Nexora Solution'),
            'phone' => config('dxclinic.developer_phone') ?: '+91 98765 43210',
            'email' => config('dxclinic.developer_email', 'contact@nexorasolutionsind.co.in'),
            'website' => config('dxclinic.developer_website', 'https://nexorasolutionsind.co.in/'),
            'onlineTermsUrl' => 'https://nexorasolutionsind.co.in/terms-and-conditions.php',
            'copyright' => '© 2026 Nexora Solution. Licensed under GNU AGPL-3.0.',
            'effectiveDate' => 'September 24, 2026',
        ]);
    }
}
