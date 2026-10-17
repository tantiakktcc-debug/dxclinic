<?php

namespace App\Livewire;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ConsultationBill;
use App\Models\DiagnosticBill;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\PatientIdentityService;
use Livewire\Component;
use Livewire\WithPagination;

class PatientManager extends Component
{
    use WithPagination;

    // Search & filters
    public string $search = '';
    public string $filterSex = '';
    public string $filterCity = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    // View state: 'list', 'history', 'edit'
    public string $activeView = 'list';

    // Selected patient for history/edit
    public ?int $selectedPatientId = null;
    public ?Patient $selectedPatient = null;

    // Patient edit form
    public string $editName = '';
    public int $editAge = 0;
    public string $editSex = '';
    public string $editMobile = '';
    public string $editAddress = '';
    public string $editCity = '';

    // Stats
    public int $totalPatients = 0;
    public int $todayNewPatients = 0;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterSex' => ['except' => ''],
        'filterCity' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        $clinic = \App\Services\TenantContext::clinic();
        if ($clinic) {
            $this->totalPatients = Patient::where('clinic_id', $clinic->id)->count();
            $this->todayNewPatients = Patient::where('clinic_id', $clinic->id)
                ->whereDate('created_at', now()->toDateString())
                ->count();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSex(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCity(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * View patient history
     */
    public function viewHistory(int $patientId): void
    {
        $clinic = \App\Services\TenantContext::clinic();
        $this->selectedPatientId = $patientId;
        $this->selectedPatient = $clinic ? Patient::where('clinic_id', $clinic->id)->with(['clinic'])->find($patientId) : null;
        abort_unless($this->selectedPatient, 403, 'Unauthorized patient access.');
        $this->activeView = 'history';
    }

    /**
     * Open patient profile edit
     */
    public function editProfile(int $patientId): void
    {
        $clinic = \App\Services\TenantContext::clinic();
        $this->selectedPatientId = $patientId;
        $this->selectedPatient = $clinic ? Patient::where('clinic_id', $clinic->id)->find($patientId) : null;
        abort_unless($this->selectedPatient, 403, 'Unauthorized patient access.');

        if ($this->selectedPatient) {
            $this->editName = $this->selectedPatient->name;
            $this->editAge = $this->selectedPatient->age;
            $this->editSex = $this->selectedPatient->sex;
            $this->editMobile = $this->selectedPatient->mobile;
            $this->editAddress = $this->selectedPatient->address;
            $this->editCity = $this->selectedPatient->city;
        }

        $this->activeView = 'edit';
    }

    /**
     * Save patient profile edits
     */
    public function saveProfile(): void
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editAge' => 'required|integer|min:1|max:130',
            'editSex' => 'required|in:MALE,FEMALE,OTHER',
            'editMobile' => 'required|string|min:10',
            'editAddress' => 'nullable|string|max:500',
            'editCity' => 'required|string|max:100',
        ]);

        if ($this->selectedPatient) {
            $this->selectedPatient->update([
                'name' => trim($this->editName),
                'age' => $this->editAge,
                'sex' => $this->editSex,
                'mobile' => preg_replace('/[^0-9]/', '', $this->editMobile),
                'address' => trim($this->editAddress),
                'city' => trim($this->editCity),
            ]);

            session()->flash('success', "Patient profile updated: {$this->selectedPatient->patient_id}");
            $this->loadStats();
            $this->backToList();
        }
    }

    /**
     * Return to patient list view
     */
    public function backToList(): void
    {
        $this->activeView = 'list';
        $this->selectedPatientId = null;
        $this->selectedPatient = null;
        $this->resetValidation();
    }

    /**
     * Get patient visit history with all details
     */
    public function getPatientHistoryProperty(): array
    {
        if (!$this->selectedPatientId) return [];

        $visits = Visit::where('patient_id', $this->selectedPatientId)
            ->with(['appointment', 'vital', 'consultationBill.payment', 'diagnosticBills.items', 'diagnosticBills.order.report', 'diagnosticBills.order.specimens', 'doctor'])
            ->orderBy('visit_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $visits->map(function ($visit) {
            return [
                'id' => $visit->id,
                'date' => $visit->visit_date?->format('d/m/Y') ?? '—',
                'session' => $visit->session_type ?? '—',
                'token' => $visit->appointment?->formatted_token ?? '—',
                'status' => $visit->status ?? '—',
                'doctor' => $visit->doctor?->name ?? '—',
                'arrived_at' => $visit->arrived_at?->format('H:i') ?? '—',
                'completed_at' => $visit->completed_at?->format('H:i') ?? '—',
                'vital' => $visit->vital ? [
                    'bp' => ($visit->vital->systolic_bp && $visit->vital->diastolic_bp) ? "{$visit->vital->systolic_bp}/{$visit->vital->diastolic_bp}" : '—',
                    'pulse' => $visit->vital->pulse ?? '—',
                    'weight' => $visit->vital->weight ?? '—',
                    'temp' => $visit->vital->temperature ?? '—',
                    'spo2' => $visit->vital->spo2 ?? '—',
                    'sugar' => $visit->vital->blood_sugar ?? '—',
                ] : null,
                'consultation_bill' => $visit->consultationBill ? [
                    'id' => $visit->consultationBill->id,
                    'bill_number' => $visit->consultationBill->bill_number,
                    'gross' => number_format($visit->consultationBill->gross_amount, 2),
                    'net' => number_format($visit->consultationBill->net_amount, 2),
                    'waiver' => number_format($visit->consultationBill->waiver_amount, 2),
                    'status' => $visit->consultationBill->status,
                    'payment_method' => $visit->consultationBill->payment?->payment_method ?? '—',
                    'payment_status' => $visit->consultationBill->payment?->status ?? '—',
                ] : null,
                'diagnostic_bills' => $visit->diagnosticBills->map(function ($db) {
                    $order = $db->order;
                    $specimen = $order?->specimens?->whereNotIn('status', ['REJECTED', 'CANCELLED'])->sortByDesc('id')->first();
                    $report = $order?->report;
                    return [
                        'id' => $db->id,
                        'bill_number' => $db->bill_number,
                        'net' => number_format($db->net_amount, 2),
                        'items' => $db->items->pluck('test_name')->implode(', '),
                        'order_number' => $order?->order_number,
                        'order_status' => $order?->order_status,
                        'specimen_id' => $specimen?->specimen_identifier,
                        'specimen_status' => $specimen?->status,
                        'report_id' => ($report && $report->status === 'RELEASED') ? $report->id : null,
                        'report_number' => ($report && $report->status === 'RELEASED') ? $report->report_number : null,
                    ];
                })->toArray(),
                'appointment_id' => $visit->appointment_id,
            ];
        })->toArray();
    }

    /**
     * Get patient's released diagnostic lab reports (Revision 36)
     */
    public function getDiagnosticLabReportsProperty(): array
    {
        if (!$this->selectedPatientId) return [];

        $reports = \App\Models\DiagnosticReport::where('patient_id', $this->selectedPatientId)
            ->where('status', 'RELEASED')
            ->with(['doctor', 'releasedBy', 'order.items.diagnosticItem', 'order.specimens'])
            ->orderBy('released_at', 'desc')
            ->get();

        return $reports->map(function ($rep) {
            return [
                'id' => $rep->id,
                'report_number' => $rep->report_number,
                'order_number' => $rep->order?->order_number ?? '—',
                'doctor' => $rep->doctor?->name ?? '—',
                'released_at' => $rep->released_at?->format('d/m/Y H:i') ?? '—',
                'released_by' => $rep->releasedBy?->name ?? '—',
                'tests' => $rep->order?->items?->map(function($i) { return $i->test_name; })->implode(', ') ?? '—',
                'specimen_ids' => $rep->order?->specimens?->pluck('specimen_identifier')->implode(', ') ?? '—',
                'status' => $rep->status,
            ];
        })->toArray();
    }

    /**
     * Get distinct cities for filter dropdown
     */
    public function getCitiesProperty(): array
    {
        $clinic = \App\Services\TenantContext::clinic();
        if (!$clinic) return [];

        return Patient::where('clinic_id', $clinic->id)
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->toArray();
    }

    public function render()
    {
        $clinic = \App\Services\TenantContext::clinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');
        $patients = collect();

        if ($clinic) {
            $query = Patient::where('clinic_id', $clinic->id);

            // Search filter
            if ($this->search !== '') {
                $searchTerm = $this->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('patient_id', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('mobile', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('city', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Sex filter
            if ($this->filterSex !== '') {
                $query->where('sex', $this->filterSex);
            }

            // City filter
            if ($this->filterCity !== '') {
                $query->where('city', $this->filterCity);
            }

            $patients = $query->orderBy($this->sortField, $this->sortDirection)
                ->paginate(20);
        }

        return view('livewire.patient-manager', [
            'patients' => $patients,
        ])->layout('components.layouts.app', ['title' => 'Patient Management']);
    }
}
