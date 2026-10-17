<?php

namespace App\Livewire\Queue;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Services\ClinicClock;
use App\Services\QueueViewService;
use App\Services\TenantContext;
use Livewire\Attributes\On;
use Livewire\Component;

class LiveQueue extends Component
{
    public ?string $public_slug = null;
    public int $clinicId = 0;
    public string $filterDate = '';
    public string $filterSession = 'ALL';
    public ?int $filterDoctorId = null;
    public string $connectionState = 'CONNECTED';
    public string $lastUpdated = '';
    public bool $soundEnabled = false;

    // New Token Alert Toast
    public ?int $newlyAddedTokenNumber = null;
    public string $newlyAddedTokenSource = '';
    public string $newlyAddedPatientName = '';
    public bool $showNewTokenToast = false;

    // Previous max token tracked to detect new bookings in real time
    public int $previousMaxToken = 0;

    public function mount(?string $public_slug = null, ?QueueViewService $queueService = null): void
    {
        $queueService = $queueService ?? app(QueueViewService::class);
        $user = auth()->user();
        abort_unless($user, 401, 'Unauthenticated.');

        // Role authorization check (Section 33, 64)
        $allowedRoles = ['ADMIN', 'OPERATOR', 'DOCTOR', 'SUPERADMIN'];
        abort_unless(in_array($user->role, $allowedRoles, true), 403, 'Unauthorized role for Live Queue.');

        $this->public_slug = $public_slug;
        $reqDate = request()->query('date');
        $this->filterDate = $reqDate ? \Carbon\Carbon::parse($reqDate)->toDateString() : ClinicClock::today()->toDateString();
        $this->lastUpdated = now()->format('H:i:s');

        // Canonical URL enforcement (Section 9, 11):
        // If accessed via /queue/live without public_slug, redirect to canonical /clinic/{public_slug}/queue/live
        if (empty($this->public_slug)) {
            $resolved = $this->resolveClinic();
            if ($resolved && !empty($resolved->public_slug)) {
                $params = ['public_slug' => $resolved->public_slug];
                if ($reqDate) {
                    $params['date'] = $reqDate;
                }
                $this->redirect(route('queue.live', $params));
                return;
            }
        }

        // Doctor role isolation: Default strictly to own doctor ID (Section 33, 41, 64)
        if ($user->role === 'DOCTOR') {
            $this->filterDoctorId = $user->doctor_id ? (int) $user->doctor_id : null;
        }

        // Initialize previousMaxToken
        $clinic = $this->resolveClinic();
        if ($clinic) {
            $this->clinicId = (int) $clinic->id;
            $data = $queueService->getQueueData($clinic, $this->filterDate, $this->filterSession, $this->filterDoctorId, $user);
            $items = $data['items'] ?? collect();
            $this->previousMaxToken = $items->max('token_number') ?? 0;
        }
    }

    public function resolveClinic(): ?Clinic
    {
        $slug = $this->public_slug ?: request()->route('public_slug');

        if (!empty($slug)) {
            $clinic = Clinic::where('public_slug', strtolower(trim($slug)))->first();
            if (!$clinic) {
                abort(404, 'Clinic not found.');
            }
            // Ensure authenticated user belongs to this clinic (HTTP 403 strict cross-clinic isolation)
            $user = auth()->user();
            if ($user && $user->role !== 'SUPERADMIN' && (int) $user->clinic_id !== (int) $clinic->id) {
                abort(403, 'Unauthorized cross-clinic Live Queue access.');
            }
            return $clinic;
        }

        $user = auth()->user();
        if ($user && $user->role !== 'SUPERADMIN') {
            return $user->clinic ?? Clinic::find($user->clinic_id) ?? TenantContext::clinic();
        }

        return TenantContext::clinic() ?? $user?->clinic;
    }

    public function refreshQueue(QueueViewService $queueService): void
    {
        $clinic = $this->resolveClinic();
        if (!$clinic) {
            $this->connectionState = 'OFFLINE';
            return;
        }

        $user = auth()->user();
        $data = $queueService->getQueueData($clinic, $this->filterDate, $this->filterSession, $this->filterDoctorId, $user);
        $items = $data['items'] ?? collect();

        // Check for new token arrival (Section 55, 105)
        $currentMaxToken = $items->max('token_number') ?? 0;
        if ($currentMaxToken > $this->previousMaxToken && $this->previousMaxToken > 0) {
            $newest = $items->firstWhere('token_number', $currentMaxToken);
            if ($newest) {
                $this->newlyAddedTokenNumber = $newest['token_number'];
                $this->newlyAddedTokenSource = $newest['booking_source'];
                $this->newlyAddedPatientName = $newest['patient_name'];
                $this->showNewTokenToast = true;

                // Dispatch browser event to play sound if enabled
                if ($this->soundEnabled) {
                    $this->dispatch('play-token-sound');
                }
            }
        }

        if ($currentMaxToken > $this->previousMaxToken) {
            $this->previousMaxToken = $currentMaxToken;
        }

        $this->lastUpdated = now()->format('H:i:s');
        $this->connectionState = 'CONNECTED';
    }

    #[On('echo-private:clinic.{clinicId}.queue,QueueUpdated')]
    public function onQueueUpdated(): void
    {
        $this->refreshQueue(app(QueueViewService::class));
    }

    #[On('refresh-live-queue')]
    public function handleManualRefresh(): void
    {
        $this->refreshQueue(app(QueueViewService::class));
    }

    public function markSeen(int $appointmentId, ?\App\Services\VisitService $visitService = null): void
    {
        \Illuminate\Support\Facades\Log::info("LIVEQUEUE MARKSEEN INVOKED: app_id={$appointmentId}");
        $visitService = $visitService ?? app(\App\Services\VisitService::class);
        $user = auth()->user();
        abort_unless($user, 401, 'Unauthenticated.');

        $clinic = $this->resolveClinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        // Enforce that appointment belongs strictly to this clinic
        $appointment = \App\Models\Appointment::where('clinic_id', $clinic->id)
            ->where('id', $appointmentId)
            ->first();
        abort_unless($appointment, 404, 'Appointment not found in authenticated clinic.');

        // Doctor role isolation: Doctor can only mark their own appointments as seen
        if ($user->role === 'DOCTOR' && (int) $appointment->doctor_id !== (int) $user->doctor_id) {
            abort(403, 'Unauthorized: Doctors may only mark their own assigned appointments as seen.');
        }

        // Authoritative consultation fee gate
        $feeStatus = app(\App\Services\FeeStatusResolver::class)->resolve($appointment);
        if ($feeStatus !== 'PAID') {
            abort(422, "Cannot mark patient as seen. Consultation fee status is {$feeStatus}. Consultation fee must be PAID first via Fee & Pad.");
        }

        // Authoritative domain transition via VisitService
        $visitService->markSeen($appointment, $user);

        // Re-query authoritative state
        $this->refreshQueue(app(QueueViewService::class));
    }

    public function updatedFilterDate(): void
    {
        $this->refreshQueue(app(QueueViewService::class));
    }

    public function setSessionFilter(string $session): void
    {
        $this->filterSession = strtoupper(trim($session));
    }

    public function setDoctorFilter(?int $doctorId): void
    {
        // Doctors cannot switch to another doctor's queue (Section 41, 64)
        if (auth()->user()?->role === 'DOCTOR') {
            return;
        }

        $this->filterDoctorId = $doctorId ? (int) $doctorId : null;
    }

    public function toggleSound(): void
    {
        $this->soundEnabled = !$this->soundEnabled;
    }

    public function dismissToast(): void
    {
        $this->showNewTokenToast = false;
    }

    public function render(QueueViewService $queueService)
    {
        $clinic = $this->resolveClinic();
        abort_unless($clinic, 403, 'Unauthorized clinic context.');

        $user = auth()->user();
        abort_unless($user, 401, 'Unauthenticated.');

        // Hardened server-side doctor role isolation
        if ($user->role === 'DOCTOR') {
            $this->filterDoctorId = $user->doctor_id ? (int) $user->doctor_id : null;
        }

        $queueData = $queueService->getQueueData(
            $clinic,
            $this->filterDate,
            $this->filterSession,
            $this->filterDoctorId,
            $user
        );

        $activeDoctors = Doctor::where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->when($user->role === 'DOCTOR', function ($q) use ($user) {
                $q->where('id', $user->doctor_id);
            })
            ->orderBy('name')
            ->get();

        $scheduleService = app(\App\Services\ClinicScheduleService::class);
        $sessions = $scheduleService->getSessionsForDate($clinic, $this->filterDate);

        return view('livewire.queue.live-queue', [
            'clinic' => $clinic,
            'queue' => $queueData['items'],
            'metrics' => $queueData['metrics'],
            'activeDoctors' => $activeDoctors,
            'sessions' => $sessions,
            'userRole' => $user->role,
        ])->layout('components.layouts.live-queue');
    }
}
