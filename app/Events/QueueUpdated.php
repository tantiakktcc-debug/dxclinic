<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $clinicId;
    public ?int $appointmentId;
    public string $eventType;
    public ?int $tokenNumber;
    public ?string $formattedToken;
    public string $appointmentDate;
    public ?int $doctorId;
    public string $occurredAt;
    public int $version;

    /**
     * Create a new event instance.
     * Payload is minimal and safe (does not expose full patient history/records).
     */
    public function __construct(
        int $clinicId,
        string $appointmentDate,
        ?int $appointmentId = null,
        string $eventType = 'UPDATED',
        ?int $tokenNumber = null,
        ?string $formattedToken = null,
        ?int $doctorId = null
    ) {
        $this->clinicId = $clinicId;
        $this->appointmentDate = $appointmentDate;
        $this->appointmentId = $appointmentId;
        $this->eventType = $eventType;
        $this->tokenNumber = $tokenNumber;
        $this->formattedToken = $formattedToken;
        $this->doctorId = $doctorId;
        $this->occurredAt = now()->toIso8601String();
        $this->version = (int) (microtime(true) * 1000);
    }

    /**
     * Factory method from Appointment instance
     */
    public static function fromAppointment(Appointment $appointment, string $eventType = 'BOOKED'): self
    {
        return new self(
            clinicId: (int) $appointment->clinic_id,
            appointmentDate: $appointment->appointment_date->toDateString(),
            appointmentId: (int) $appointment->id,
            eventType: $eventType,
            tokenNumber: (int) $appointment->token_number,
            formattedToken: (string) $appointment->formatted_token,
            doctorId: $appointment->doctor_id ? (int) $appointment->doctor_id : null
        );
    }

    /**
     * Broadcast on private channels strictly scoped to the tenant clinic
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('clinic.' . $this->clinicId . '.queue'),
        ];

        if ($this->doctorId) {
            $channels[] = new PrivateChannel('clinic.' . $this->clinicId . '.doctor.' . $this->doctorId . '.queue');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'QueueUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'clinic_id' => $this->clinicId,
            'appointment_date' => $this->appointmentDate,
            'appointment_id' => $this->appointmentId,
            'event_type' => $this->eventType,
            'token_number' => $this->tokenNumber,
            'formatted_token' => $this->formattedToken,
            'doctor_id' => $this->doctorId,
            'occurred_at' => $this->occurredAt,
            'version' => $this->version,
        ];
    }
}
