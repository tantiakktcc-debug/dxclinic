<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Domain\WhatsAppBookingStateMachine;

class PatientAssistantService
{
    public function __construct(
        protected WhatsAppBookingStateMachine $stateMachine
    ) {
    }

    public function processWebMessage(Clinic $clinic, string $phoneOrSession, string $message): array
    {
        $connection = WhatsAppConnection::where('clinic_id', $clinic->id)->where('is_active', true)->first();
        
        if (!$connection) {
            $connection = WhatsAppConnection::firstOrCreate(
                ['clinic_id' => $clinic->id],
                [
                    'session_name' => 'WEB_DEFAULT',
                    'is_active' => true,
                    'status' => 'CONNECTED',
                ]
            );
        }

        $conversation = \App\Models\WhatsAppConversation::firstOrCreate(
            [
                'clinic_id' => $clinic->id,
                'wa_id' => $phoneOrSession,
            ],
            [
                'whatsapp_connection_id' => $connection->id,
                'current_state' => 'NEW',
                'last_patient_message_at' => now(),
            ]
        );

        $conversation->update(['last_patient_message_at' => now()]);

        return $this->stateMachine->process($conversation, $message, $clinic);
    }
}
