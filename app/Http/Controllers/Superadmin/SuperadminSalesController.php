<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\PlatformExpense;
use App\Models\PlatformSalesInvoice;
use App\Services\PdfGeneratorService;
use App\Services\PlatformInvoiceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SuperadminSalesController extends Controller
{
    protected PdfGeneratorService $pdfService;

    public function __construct(PdfGeneratorService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Helper to resolve start & end date for predefined filter presets
     */
    protected function resolveDateRange(Request $request): array
    {
        $range = $request->query('range', 'all');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $now = Carbon::now('Asia/Kolkata');
        $start = null;
        $end = null;

        switch ($range) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'yesterday':
                $start = $now->copy()->subDay()->startOfDay();
                $end = $now->copy()->subDay()->endOfDay();
                break;
            case 'this_week':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                break;
            case 'this_month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
            case 'quarter':
                $start = $now->copy()->firstOfQuarter();
                $end = $now->copy()->lastOfQuarter();
                break;
            case 'half_year':
                $start = $now->month <= 6
                    ? Carbon::create($now->year, 1, 1)->startOfDay()
                    : Carbon::create($now->year, 7, 1)->startOfDay();
                $end = $now->month <= 6
                    ? Carbon::create($now->year, 6, 30)->endOfDay()
                    : Carbon::create($now->year, 12, 31)->endOfDay();
                break;
            case 'financial_year':
                // Indian FY runs April 1 to March 31
                $fyYear = $now->month >= 4 ? $now->year : $now->year - 1;
                $start = Carbon::create($fyYear, 4, 1)->startOfDay();
                $end = Carbon::create($fyYear + 1, 3, 31)->endOfDay();
                break;
            case 'custom':
                if ($fromDate) {
                    $start = Carbon::parse($fromDate, 'Asia/Kolkata')->startOfDay();
                }
                if ($toDate) {
                    $end = Carbon::parse($toDate, 'Asia/Kolkata')->endOfDay();
                }
                break;
            default:
                // 'all' or no date constraint
                break;
        }

        return [$start, $end, $range];
    }

    /**
     * Superadmin Sales Dashboard (/superadmin/sales)
     */
    public function salesDashboard(Request $request)
    {
        [$startDate, $endDate, $range] = $this->resolveDateRange($request);

        $query = PlatformSalesInvoice::with('clinic');

        // Apply Date Range
        if ($startDate && $endDate) {
            $query->whereBetween('invoice_date', [$startDate->toDateString(), $endDate->toDateString()]);
        } elseif ($startDate) {
            $query->where('invoice_date', '>=', $startDate->toDateString());
        } elseif ($endDate) {
            $query->where('invoice_date', '<=', $endDate->toDateString());
        }

        // Filters
        if ($clinicId = $request->query('clinic_id')) {
            $query->where('clinic_id', $clinicId);
        }
        if ($clinicCode = $request->query('clinic_code')) {
            $query->whereHas('clinic', function ($q) use ($clinicCode) {
                $q->where('clinic_code', strtoupper($clinicCode));
            });
        }
        if ($saleType = $request->query('sale_type')) {
            $query->where('sale_type', $saleType);
        }
        if ($paymentMethod = $request->query('payment_method')) {
            $query->where('payment_method', $paymentMethod);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $allInvoices = (clone $query)->get();

        // Financial KPIs (Excluding cancelled invoices from sales calculations)
        $activeInvoices = $allInvoices->where('status', '!=', 'CANCELLED');

        $grossSales = (float) $activeInvoices->sum('subtotal');
        $discounts = (float) $activeInvoices->sum('discount');
        $netSales = max(0.0, $grossSales - $discounts);

        // Collections: paid invoices within the period
        $collected = (float) $activeInvoices->where('status', 'PAID')->sum('net_amount');
        $outstanding = (float) $activeInvoices->where('status', 'ISSUED')->sum('net_amount');
        $refunds = 0.00; // Authoritative system has no refund transactions currently

        // Platform Expenses (Filtered by same date range)
        $expenseQuery = PlatformExpense::query();
        if ($startDate && $endDate) {
            $expenseQuery->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);
        } elseif ($startDate) {
            $expenseQuery->where('expense_date', '>=', $startDate->toDateString());
        } elseif ($endDate) {
            $expenseQuery->where('expense_date', '<=', $endDate->toDateString());
        }
        $platformExpenses = (float) $expenseQuery->sum('amount');

        // Profit formulas (Specification 15)
        $netRevenue = max(0.0, $netSales - $refunds);
        $operatingProfit = $netRevenue - $platformExpenses;

        // Breakdown by Sale Type
        $salesByType = [
            'INITIAL_LICENSE' => (float) $activeInvoices->where('sale_type', 'INITIAL_LICENSE')->sum('net_amount'),
            'LICENSE_RENEWAL' => (float) $activeInvoices->where('sale_type', 'LICENSE_RENEWAL')->sum('net_amount'),
            'DOCTOR_ADDON' => (float) $activeInvoices->where('sale_type', 'DOCTOR_ADDON')->sum('net_amount'),
        ];

        // Breakdown by Payment Method
        $collectedByMethod = [
            'CASH' => (float) $activeInvoices->where('status', 'PAID')->where('payment_method', 'CASH')->sum('net_amount'),
            'UPI' => (float) $activeInvoices->where('status', 'PAID')->where('payment_method', 'UPI')->sum('net_amount'),
            'BANK_TRANSFER' => (float) $activeInvoices->where('status', 'PAID')->where('payment_method', 'BANK_TRANSFER')->sum('net_amount'),
            'CHEQUE' => (float) $activeInvoices->where('status', 'PAID')->where('payment_method', 'CHEQUE')->sum('net_amount'),
            'OTHER' => (float) $activeInvoices->where('status', 'PAID')->whereNotIn('payment_method', ['CASH', 'UPI', 'BANK_TRANSFER', 'CHEQUE'])->sum('net_amount'),
        ];

        // Recent Invoices (limit 10 for dashboard preview)
        $recentInvoices = (clone $query)->orderBy('invoice_date', 'desc')->orderBy('id', 'desc')->take(10)->get();

        $clinics = Clinic::orderBy('name')->get(['id', 'name', 'clinic_code']);

        return view('superadmin.sales.dashboard', compact(
            'grossSales',
            'discounts',
            'netSales',
            'collected',
            'outstanding',
            'refunds',
            'platformExpenses',
            'netRevenue',
            'operatingProfit',
            'salesByType',
            'collectedByMethod',
            'recentInvoices',
            'clinics',
            'range',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Invoice List (/superadmin/invoices)
     */
    public function invoicesList(Request $request)
    {
        [$startDate, $endDate, $range] = $this->resolveDateRange($request);

        $query = PlatformSalesInvoice::with(['clinic', 'license.plan']);

        if ($startDate && $endDate) {
            $query->whereBetween('invoice_date', [$startDate->toDateString(), $endDate->toDateString()]);
        }
        if ($clinicId = $request->query('clinic_id')) {
            $query->where('clinic_id', $clinicId);
        }
        if ($saleType = $request->query('sale_type')) {
            $query->where('sale_type', $saleType);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('clinic', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                         ->orWhere('clinic_code', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->orderBy('invoice_date', 'desc')->orderBy('id', 'desc')->paginate(20)->withQueryString();
        $clinics = Clinic::orderBy('name')->get(['id', 'name', 'clinic_code']);

        return view('superadmin.sales.invoices', compact('invoices', 'clinics', 'range', 'startDate', 'endDate'));
    }

    /**
     * Invoice Detail (/superadmin/invoices/{invoice})
     */
    public function invoiceDetail(PlatformSalesInvoice $invoice)
    {
        $invoice->load(['clinic', 'license.plan', 'items', 'createdBy', 'cancelledBy']);

        return view('superadmin.sales.invoice_detail', compact('invoice'));
    }

    /**
     * Invoice Print View (/superadmin/invoices/{invoice}/print)
     */
    public function invoicePrint(PlatformSalesInvoice $invoice)
    {
        $invoice->load(['clinic', 'license.plan', 'items', 'createdBy']);

        return view('superadmin.sales.invoice_print', compact('invoice'));
    }

    /**
     * Invoice PDF Download/Stream (/superadmin/invoices/{invoice}/pdf)
     */
    public function invoicePdf(PlatformSalesInvoice $invoice, Request $request)
    {
        $invoice->load(['clinic', 'license.plan', 'items', 'createdBy']);

        $filename = "invoice-{$invoice->invoice_number}.pdf";
        $download = $request->boolean('download', false);

        return $this->pdfService->generateFromView(
            'superadmin.sales.invoice_pdf',
            ['invoice' => $invoice],
            $filename,
            'a4',
            'portrait',
            $download
        );
    }

    /**
     * Controlled Invoice Cancellation
     */
    public function cancelInvoice(PlatformSalesInvoice $invoice, Request $request)
    {
        $request->validate([
            'cancellation_reason' => 'required|string|min:5|max:500',
        ]);

        $cancelledBy = Auth::guard('developer')->id();
        PlatformInvoiceService::cancelInvoice($invoice, $request->cancellation_reason, $cancelledBy);

        return redirect()->route('superadmin.invoices.show', $invoice->id)
            ->with('success', "Invoice {$invoice->invoice_number} has been cancelled successfully.");
    }

    /**
     * Collection Report (/superadmin/collections)
     */
    public function collections(Request $request)
    {
        [$startDate, $endDate, $range] = $this->resolveDateRange($request);
        $viewType = $request->query('view_type', 'transactions'); // transactions, daily, monthly, fy

        $query = PlatformSalesInvoice::with('clinic')
            ->where('status', 'PAID')
            ->whereNotNull('payment_date');

        if ($startDate && $endDate) {
            $query->whereBetween('payment_date', [$startDate->toDateString(), $endDate->toDateString()]);
        } elseif ($startDate) {
            $query->where('payment_date', '>=', $startDate->toDateString());
        } elseif ($endDate) {
            $query->where('payment_date', '<=', $endDate->toDateString());
        }

        if ($clinicId = $request->query('clinic_id')) {
            $query->where('clinic_id', $clinicId);
        }
        if ($paymentMethod = $request->query('payment_method')) {
            $query->where('payment_method', $paymentMethod);
        }

        $allCollections = (clone $query)->get();

        $totalCollection = (float) $allCollections->sum('net_amount');
        $cashTotal = (float) $allCollections->where('payment_method', 'CASH')->sum('net_amount');
        $upiTotal = (float) $allCollections->where('payment_method', 'UPI')->sum('net_amount');
        $bankTransferTotal = (float) $allCollections->where('payment_method', 'BANK_TRANSFER')->sum('net_amount');
        $chequeTotal = (float) $allCollections->where('payment_method', 'CHEQUE')->sum('net_amount');
        $otherTotal = (float) $allCollections->whereNotIn('payment_method', ['CASH', 'UPI', 'BANK_TRANSFER', 'CHEQUE'])->sum('net_amount');
        $refunds = 0.00;
        $netCollection = $totalCollection - $refunds;

        $groupedData = collect();

        if ($viewType === 'daily') {
            $groupedData = $allCollections->groupBy(function ($item) {
                return $item->payment_date ? $item->payment_date->toDateString() : 'N/A';
            })->map(function ($items, $date) {
                return [
                    'label' => $date,
                    'count' => $items->count(),
                    'total' => $items->sum('net_amount'),
                    'cash' => $items->where('payment_method', 'CASH')->sum('net_amount'),
                    'upi' => $items->where('payment_method', 'UPI')->sum('net_amount'),
                    'bank' => $items->where('payment_method', 'BANK_TRANSFER')->sum('net_amount'),
                    'cheque' => $items->where('payment_method', 'CHEQUE')->sum('net_amount'),
                    'other' => $items->whereNotIn('payment_method', ['CASH', 'UPI', 'BANK_TRANSFER', 'CHEQUE'])->sum('net_amount'),
                ];
            })->sortByDesc('label');
        } elseif ($viewType === 'monthly') {
            $groupedData = $allCollections->groupBy(function ($item) {
                return $item->payment_date ? $item->payment_date->format('Y-m') : 'N/A';
            })->map(function ($items, $month) {
                return [
                    'label' => $month,
                    'count' => $items->count(),
                    'total' => $items->sum('net_amount'),
                    'cash' => $items->where('payment_method', 'CASH')->sum('net_amount'),
                    'upi' => $items->where('payment_method', 'UPI')->sum('net_amount'),
                    'bank' => $items->where('payment_method', 'BANK_TRANSFER')->sum('net_amount'),
                    'cheque' => $items->where('payment_method', 'CHEQUE')->sum('net_amount'),
                    'other' => $items->whereNotIn('payment_method', ['CASH', 'UPI', 'BANK_TRANSFER', 'CHEQUE'])->sum('net_amount'),
                ];
            })->sortByDesc('label');
        } elseif ($viewType === 'fy') {
            $groupedData = $allCollections->groupBy(function ($item) {
                if (!$item->payment_date) return 'N/A';
                $dt = $item->payment_date;
                $fy = $dt->month >= 4 ? $dt->year . '-' . ($dt->year + 1) : ($dt->year - 1) . '-' . $dt->year;
                return "FY {$fy}";
            })->map(function ($items, $fy) {
                return [
                    'label' => $fy,
                    'count' => $items->count(),
                    'total' => $items->sum('net_amount'),
                    'cash' => $items->where('payment_method', 'CASH')->sum('net_amount'),
                    'upi' => $items->where('payment_method', 'UPI')->sum('net_amount'),
                    'bank' => $items->where('payment_method', 'BANK_TRANSFER')->sum('net_amount'),
                    'cheque' => $items->where('payment_method', 'CHEQUE')->sum('net_amount'),
                    'other' => $items->whereNotIn('payment_method', ['CASH', 'UPI', 'BANK_TRANSFER', 'CHEQUE'])->sum('net_amount'),
                ];
            })->sortByDesc('label');
        }

        $transactions = $query->orderBy('payment_date', 'desc')->orderBy('id', 'desc')->paginate(25)->withQueryString();
        $clinics = Clinic::orderBy('name')->get(['id', 'name', 'clinic_code']);

        return view('superadmin.sales.collections', compact(
            'totalCollection',
            'cashTotal',
            'upiTotal',
            'bankTransferTotal',
            'chequeTotal',
            'otherTotal',
            'refunds',
            'netCollection',
            'transactions',
            'groupedData',
            'viewType',
            'clinics',
            'range',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Per-Clinic Commercial History (/superadmin/clinics/{clinic}/sales)
     */
    public function clinicSales(Clinic $clinic)
    {
        $invoices = $clinic->platformInvoices()->with(['license.plan', 'items'])->get();
        $renewals = $clinic->licenseRenewals()->with(['plan', 'renewedBy'])->get();
        $addons = $clinic->doctorSeatAddons()->with(['createdBy'])->get();
        $licenses = $clinic->licenses()->with(['plan', 'issuedBy'])->orderBy('id', 'asc')->get();

        // Financial Totals for Clinic
        $grossSales = (float) $invoices->where('status', '!=', 'CANCELLED')->sum('subtotal');
        $discounts = (float) $invoices->where('status', '!=', 'CANCELLED')->sum('discount');
        $netSales = max(0.0, $grossSales - $discounts);
        $collected = (float) $invoices->where('status', 'PAID')->sum('net_amount');
        $outstanding = (float) $invoices->where('status', 'ISSUED')->sum('net_amount');
        $refunds = 0.00;

        // Build Chronological Commercial Timeline
        $timeline = collect();

        foreach ($licenses as $lic) {
            $timeline->push([
                'type' => $lic->is_renewal ? 'RENEWAL_LICENSE' : 'INITIAL_LICENSE',
                'date' => $lic->issued_at ? Carbon::parse($lic->issued_at) : Carbon::parse($lic->created_at),
                'title' => ($lic->is_renewal ? "Renewal License (#{$lic->renewal_number})" : "Initial License Purchase") . " — " . ($lic->plan?->name ?? 'Plan'),
                'amount' => (float) $lic->net_amount,
                'status' => $lic->status,
                'details' => "Valid: " . ($lic->start_date?->format('d M Y') ?? '') . " to " . ($lic->end_date?->format('d M Y') ?? '') . " | Key: {$lic->masked_key}",
                'link' => null,
            ]);
        }

        foreach ($addons as $addon) {
            $timeline->push([
                'type' => 'DOCTOR_ADDON',
                'date' => Carbon::parse($addon->purchase_date),
                'title' => "Doctor Seat Add-on ({$addon->seats_count} seat(s))",
                'amount' => (float) $addon->price,
                'status' => $addon->status,
                'details' => "Paid via {$addon->payment_method} | Valid until " . ($addon->valid_until?->format('d M Y') ?? ''),
                'link' => null,
            ]);
        }

        foreach ($invoices as $inv) {
            $timeline->push([
                'type' => 'INVOICE',
                'date' => Carbon::parse($inv->invoice_date),
                'title' => "Commercial Invoice #{$inv->invoice_number} ({$inv->formattedSaleType()})",
                'amount' => (float) $inv->net_amount,
                'status' => $inv->status,
                'details' => "Subtotal: ₹" . number_format($inv->subtotal, 2) . " | Disc: ₹" . number_format($inv->discount, 2) . " | Paid: " . ($inv->payment_method ?? 'N/A'),
                'link' => route('superadmin.invoices.show', $inv->id),
            ]);
        }

        $sortedTimeline = $timeline->sortByDesc('date')->values();

        return view('superadmin.sales.clinic_sales', compact(
            'clinic',
            'invoices',
            'renewals',
            'addons',
            'licenses',
            'grossSales',
            'discounts',
            'netSales',
            'collected',
            'outstanding',
            'refunds',
            'sortedTimeline'
        ));
    }

    /**
     * Platform Expenses Management (/superadmin/expenses)
     */
    public function expenses(Request $request)
    {
        [$startDate, $endDate, $range] = $this->resolveDateRange($request);

        $query = PlatformExpense::with('createdBy');

        if ($startDate && $endDate) {
            $query->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($paymentMethod = $request->query('payment_method')) {
            $query->where('payment_method', $paymentMethod);
        }

        $totalExpenses = (float) (clone $query)->sum('amount');
        $expenses = $query->orderBy('expense_date', 'desc')->orderBy('id', 'desc')->paginate(20)->withQueryString();

        $categories = [
            'SERVER_HOSTING' => 'Cloud & Server Hosting',
            'SMS_GATEWAY' => 'SMS & Communication Gateway',
            'DOMAIN_SSL' => 'Domain & SSL Certificates',
            'MARKETING' => 'Marketing & Advertisements',
            'SALARIES' => 'Engineering & Staff Salaries',
            'OFFICE_RENT' => 'Office Space & Utilities',
            'LEGAL_COMPLIANCE' => 'Legal & Regulatory Compliance',
            'OTHER' => 'Other Platform Operations',
        ];

        return view('superadmin.sales.expenses', compact('expenses', 'totalExpenses', 'categories', 'range', 'startDate', 'endDate'));
    }

    /**
     * Store Platform Expense (Strictly NO clinic_id)
     */
    public function storeExpense(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:50',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'payment_method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['created_by'] = Auth::guard('developer')->id();

        PlatformExpense::create($validated);

        return redirect()->route('superadmin.expenses')
            ->with('success', 'Platform expense recorded successfully.');
    }
}
