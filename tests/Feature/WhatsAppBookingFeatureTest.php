<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PatientChannelIdentity;
use App\Models\TokenSequence;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessageDelivery;
use App\Models\WhatsAppWebhookEvent;
use App\Modules\WhatsApp\Application\WhatsAppService;
use App\Modules\WhatsApp\Infrastructure\MockWhatsAppProvider;
use App\Services\ClinicClock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppBookingFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Doctor $doctor;
    protected WhatsAppConnection $connection;
    protected MockWhatsAppProvider $mockProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create([
            'clinic_code' => 'NX0001',
            'public_slug' => 'sharmacare',
            'name' => 'Sharma Care Clinic',
            'phone' => '9876543210',
            'email' => 'clinic@sharma.com',
            'address' => 'Bhelupur',
            'city' => 'Varanasi',
            'is_active' => true,
            'max_advance_booking_days' => 1,
            'schedule_config' => [
                'monday' => ['morning' => ['08:00', '12:00'], 'evening' => ['17:00', '21:00']],
                'tuesday' => ['morning' => ['08:00', '12:00'], 'evening' => ['17:00', '21:00']],
                'wednesday' => ['morning' => ['08:00', '12:00'], 'evening' => ['17:00', '21:00']],
                'thursday' => ['morning' => ['08:00', '12:00'], 'evening' => ['17:00', '21:00']],
                'friday' => ['morning' => ['08:00', '12:00'], 'evening' => ['17:00', '21:00']],
                'saturday' => ['morning' => ['08:00', '12:00'], 'evening' => ['17:00', '20:00']],
                'sunday' => ['single' => ['09:00', '12:00']],
            ],
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Rajesh Sharma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'UP-MC-12345',
            'specialization' => 'General Physician',
            'contact_number' => '9876543210',
            'is_active' => true,
        ]);

        $this->connection = WhatsAppConnection::create([
            'clinic_id' => $this->clinic->id,
            'provider' => 'meta',
            'phone_number_id' => '109876543210001',
            'business_account_id' => 'waba_001',
            'display_phone_number' => '+91 98765 43210',
            'business_display_name' => 'Sharma Care WhatsApp',
            'status' => 'CONNECTED',
            'webhook_verify_token' => 'dxclinic_verify_token_secure',
            'settings' => [
                'assistant_enabled' => true,
                'booking_enabled' => true,
                'booking_today' => true,
                'booking_tomorrow' => true,
                'morning_enabled' => true,
                'evening_enabled' => true,
            ],
            'connected_at' => now(),
        ]);

        $this->mockProvider = app(MockWhatsAppProvider::class);
        $this->mockProvider->reset();
    }

    public function test_webhook_get_verification_challenge_succeeds(): void
    {
        $response = $this->get('/api/v1/webhooks/whatsapp?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'dxclinic_verify_token_secure',
            'hub_challenge' => 'challenge_code_12345',
        ]));

        $response->assertStatus(200);
        $this->assertEquals('challenge_code_12345', $response->getContent());
    }

    public function test_webhook_get_verification_fails_with_invalid_token(): void
    {
        $response = $this->get('/api/v1/webhooks/whatsapp?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong_token',
            'hub_challenge' => 'challenge_code_12345',
        ]));

        $response->assertStatus(403);
    }

    public function test_tenant_resolution_from_phone_number_id(): void
    {
        $service = app(WhatsAppService::class);
        $resolved = $service->resolveConnectionByPhoneNumberId('109876543210001');

        $this->assertNotNull($resolved);
        $this->assertEquals($this->clinic->id, $resolved->clinic_id);
    }

    public function test_inbound_greeting_returns_main_menu(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => [
                                    'phone_number_id' => '109876543210001',
                                ],
                                'messages' => [
                                    [
                                        'id' => 'wamid.msg001',
                                        'from' => '919876500001',
                                        'type' => 'text',
                                        'text' => ['body' => 'Hi'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        // Assert conversation created
        $conv = WhatsAppConversation::where('clinic_id', $this->clinic->id)
            ->where('wa_id', '919876500001')
            ->first();

        $this->assertNotNull($conv);
        $this->assertEquals('MENU', $conv->current_state);

        // Assert message recorded
        $this->assertDatabaseHas('whatsapp_messages', [
            'clinic_id' => $this->clinic->id,
            'direction' => 'INBOUND',
            'content' => 'Hi',
        ]);
    }

    public function test_webhook_idempotency_prevents_duplicate_processing(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => [
                                    'phone_number_id' => '109876543210001',
                                ],
                                'messages' => [
                                    [
                                        'id' => 'wamid.idempotent_test_001',
                                        'from' => '919876500002',
                                        'type' => 'text',
                                        'text' => ['body' => 'Hello'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Send first time
        $r1 = $this->postJson('/api/v1/webhooks/whatsapp', $payload);
        $r1->assertStatus(200);

        $event = WhatsAppWebhookEvent::where('event_id', 'wamid.idempotent_test_001')->first();
        $this->assertNotNull($event);
        $this->assertTrue((bool) $event->processed);

        // Send duplicate webhook event
        $r2 = $this->postJson('/api/v1/webhooks/whatsapp', $payload);
        $r2->assertStatus(200);

        // Verify only 1 webhook event record exists
        $this->assertEquals(1, WhatsAppWebhookEvent::where('event_id', 'wamid.idempotent_test_001')->count());

        // Verify only 1 inbound message record exists
        $this->assertEquals(1, \App\Models\WhatsAppMessage::where('provider_message_id', 'wamid.idempotent_test_001')->count());
    }

    public function test_full_whatsapp_booking_flow_allocates_unified_token(): void
    {
        $waPhone = '919876500003';
        $today = ClinicClock::today()->toDateString();

        // 1. Create an existing clinic token to verify unified sequence
        $seq = TokenSequence::create([
            'clinic_id' => $this->clinic->id,
            'token_date' => $today,
            'session_type' => 'MORNING',
            'current_token' => 5, // 5 tokens already taken by Clinic/PWA
        ]);

        // 2. Existing patient for this phone
        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => 'DXP-00042',
            'name' => 'Rohan Sharma',
            'mobile' => '9876500003',
            'age' => 30,
            'sex' => 'MALE',
            'city' => 'Varanasi',
            'address' => 'Assi Ghat',
        ]);

        // Link channel identity
        PatientChannelIdentity::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'channel' => 'WHATSAPP',
            'external_identity' => $waPhone,
            'verified_at' => now(),
        ]);

        // Initialize conversation
        $conv = WhatsAppConversation::create([
            'clinic_id' => $this->clinic->id,
            'whatsapp_connection_id' => $this->connection->id,
            'wa_id' => $waPhone,
            'current_state' => 'MENU',
        ]);

        $service = app(WhatsAppService::class);

        // Step 1: Patient asks to book
        $sendMsg = function ($text) use ($waPhone) {
            return [
                'entry' => [
                    [
                        'changes' => [
                            [
                                'value' => [
                                    'metadata' => ['phone_number_id' => '109876543210001'],
                                    'messages' => [
                                        [
                                            'id' => 'wamid.' . uniqid(),
                                            'from' => $waPhone,
                                            'type' => 'text',
                                            'text' => ['body' => $text],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        };

        $this->postJson('/api/v1/webhooks/whatsapp', $sendMsg('Book appointment'));
        $conv->refresh();
        $this->assertEquals('WAITING_DATE', $conv->current_state);

        // Step 2: Patient selects today
        $this->postJson('/api/v1/webhooks/whatsapp', $sendMsg('today'));
        $conv->refresh();
        $this->assertEquals('WAITING_SESSION', $conv->current_state);

        // Step 3: Patient selects morning session
        $this->postJson('/api/v1/webhooks/whatsapp', $sendMsg('morning'));
        $conv->refresh();
        // Since patient exists and only 1 doctor, should advance to CONFIRM_EXISTING
        $this->assertEquals('CONFIRM_EXISTING', $conv->current_state);

        // Step 4: Confirm existing patient
        $this->postJson('/api/v1/webhooks/whatsapp', $sendMsg('yes'));
        $conv->refresh();
        $this->assertEquals('WAITING_CONFIRMATION', $conv->current_state);

        // Step 5: Final Confirmation
        $this->postJson('/api/v1/webhooks/whatsapp', $sendMsg('confirm'));
        $conv->refresh();
        $this->assertEquals('BOOKED', $conv->current_state);

        // ASSERTIONS:
        // 1. Appointment was created in DB with booking_source = WHATSAPP
        $appointment = Appointment::where('clinic_id', $this->clinic->id)
            ->where('patient_id', $patient->id)
            ->where('booking_source', 'WHATSAPP')
            ->first();

        $this->assertNotNull($appointment);
        $this->assertEquals($today, $appointment->appointment_date->toDateString());
        $this->assertEquals('MORNING', $appointment->session_type);
        $this->assertEquals('WHATSAPP', $appointment->booking_source);
        $this->assertEquals('BOOKED', $appointment->status);

        // 2. UNIFIED TOKEN SEQUENCE: previous max was 5, so this MUST be 6 (Section 2, 23, 24)
        $this->assertEquals(6, $appointment->token_number);
        $this->assertEquals('006', $appointment->formatted_token);

        // 3. Outbound Notification Delivery queued asynchronously (Section 28, 29)
        $delivery = WhatsAppMessageDelivery::where('appointment_id', $appointment->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals($waPhone, $delivery->recipient_phone);
    }

    public function test_human_handoff_suspends_ai_auto_response(): void
    {
        $waPhone = '919876500004';
        $conv = WhatsAppConversation::create([
            'clinic_id' => $this->clinic->id,
            'whatsapp_connection_id' => $this->connection->id,
            'wa_id' => $waPhone,
            'current_state' => 'MENU',
        ]);

        $sendMsg = function ($text) use ($waPhone) {
            return [
                'entry' => [
                    [
                        'changes' => [
                            [
                                'value' => [
                                    'metadata' => ['phone_number_id' => '109876543210001'],
                                    'messages' => [
                                        [
                                            'id' => 'wamid.' . uniqid(),
                                            'from' => $waPhone,
                                            'type' => 'text',
                                            'text' => ['body' => $text],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        };

        // Patient asks for receptionist
        $this->postJson('/api/v1/webhooks/whatsapp', $sendMsg('I want to talk to receptionist'));
        $conv->refresh();

        $this->assertTrue((bool) $conv->human_handoff_active);
        $this->assertEquals('HUMAN_HANDOFF', $conv->current_state);

        // Subsequent message should not trigger booking
        $this->postJson('/api/v1/webhooks/whatsapp', $sendMsg('book appointment'));
        $conv->refresh();
        $this->assertTrue((bool) $conv->human_handoff_active); // Still in handoff!

        // Typing MENU returns to AI
        $this->postJson('/api/v1/webhooks/whatsapp', $sendMsg('menu'));
        $conv->refresh();
        $this->assertFalse((bool) $conv->human_handoff_active);
        $this->assertEquals('MENU', $conv->current_state);
    }

    public function test_cross_clinic_tenant_isolation_rejects_foreign_events(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => [
                                    'phone_number_id' => '999999999999999', // Unknown phone_number_id
                                ],
                                'messages' => [
                                    [
                                        'id' => 'wamid.unknown_001',
                                        'from' => '919876500099',
                                        'type' => 'text',
                                        'text' => ['body' => 'Hello'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        // Verify zero conversations created for unknown clinic
        $this->assertEquals(0, WhatsAppConversation::where('wa_id', '919876500099')->count());
    }
}
