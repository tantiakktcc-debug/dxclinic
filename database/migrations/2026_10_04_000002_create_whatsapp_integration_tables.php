<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. WhatsApp Connections (Tenant boundary: clinic_id)
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->unique()->constrained('clinics')->cascadeOnDelete();
            $table->string('provider', 50)->default('meta');
            $table->string('provider_account_id', 100)->nullable();
            $table->string('business_account_id', 100)->nullable();
            $table->string('phone_number_id', 100)->nullable()->index();
            $table->string('display_phone_number', 50)->nullable();
            $table->string('business_display_name', 150)->nullable();
            $table->enum('status', ['PENDING', 'CONNECTING', 'QR_REQUIRED', 'CONNECTED', 'DISCONNECTED', 'RECONNECTING', 'ERROR'])->default('PENDING');
            $table->string('connection_type', 50)->default('EMBEDDED_SIGNUP');
            $table->string('ownership_type', 50)->default('CLINIC_OWNED');
            $table->text('access_token_encrypted')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('webhook_verified_at')->nullable();
            $table->string('webhook_verify_token', 100)->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_successful_send_at')->nullable();
            $table->timestamps();
        });

        // 2. Patient Channel Identities (Reusing existing patients, no whatsapp_patients)
        Schema::create('patient_channel_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->enum('channel', ['WHATSAPP', 'SMS', 'EMAIL', 'TELEPHONY'])->default('WHATSAPP');
            $table->string('external_identity', 100);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'channel', 'external_identity'], 'pci_clinic_channel_ext_unique');
            $table->index(['clinic_id', 'patient_id']);
        });

        // 3. WhatsApp Conversations & Booking State Machine
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('whatsapp_connection_id')->constrained('whatsapp_connections')->cascadeOnDelete();
            $table->string('wa_id', 100); // Patient phone / WhatsApp ID
            $table->enum('current_state', [
                'NEW',
                'MENU',
                'BOOKING',
                'WAITING_DATE',
                'WAITING_SESSION',
                'WAITING_MODE',
                'WAITING_DOCTOR',
                'WAITING_PATIENT',
                'CONFIRM_EXISTING',
                'WAITING_OTP',
                'WAITING_CONFIRMATION',
                'BOOKING_PROCESSING',
                'BOOKED',
                'STATUS_LOOKUP',
                'HUMAN_HANDOFF',
                'CLOSED'
            ])->default('NEW');
            $table->json('state_payload')->nullable();
            $table->boolean('human_handoff_active')->default(false);
            $table->timestamp('human_handoff_requested_at')->nullable();
            $table->timestamp('last_patient_message_at')->nullable();
            $table->timestamp('last_outbound_message_at')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'wa_id'], 'waconv_clinic_wa_unique');
        });

        // 4. WhatsApp Messages Inbound/Outbound
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('whatsapp_conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->enum('direction', ['INBOUND', 'OUTBOUND']);
            $table->string('message_type', 50)->default('text');
            $table->string('provider_message_id', 150)->nullable()->index();
            $table->text('content')->nullable();
            $table->json('payload')->nullable();
            $table->enum('status', ['PENDING', 'SENT', 'DELIVERED', 'READ', 'FAILED'])->default('SENT');
            $table->timestamps();
        });

        // 5. WhatsApp Webhook Events (Idempotency)
        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 150)->unique();
            $table->string('phone_number_id', 100)->nullable()->index();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->string('event_type', 100);
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        // 6. WhatsApp Outbound Deliveries (Reliable asynchronous sending & retry)
        Schema::create('whatsapp_message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('recipient_phone', 50);
            $table->string('message_type', 50)->default('CONFIRMATION');
            $table->enum('status', ['PENDING', 'SENDING', 'SENT', 'FAILED', 'RETRYING', 'DEAD_LETTER'])->default('PENDING');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->text('last_error')->nullable();
            $table->string('provider_message_id', 150)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
        });

        // 7. WhatsApp Templates (Clinic/WABA scoped)
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('template_name', 100);
            $table->string('language', 10)->default('en');
            $table->string('status', 50)->default('APPROVED');
            $table->string('category', 50)->default('UTILITY');
            $table->text('body_text');
            $table->json('components')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'template_name', 'language'], 'watemp_clinic_name_lang_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_message_deliveries');
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('patient_channel_identities');
        Schema::dropIfExists('whatsapp_connections');
    }
};
