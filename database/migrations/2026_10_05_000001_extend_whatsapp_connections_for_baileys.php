<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision: Baileys Development Provider Schema Extension
 *
 * Extends whatsapp_connections to be provider-neutral.
 * Adds Baileys-specific lifecycle states and the QR token field.
 * Does NOT create duplicate connection tables.
 * Meta architecture remains fully preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            // Expand status enum to include Baileys lifecycle states
            // SQLite-compatible: drop unique, re-add after modifying
            // For MySQL: use raw ALTER to extend the enum
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                \Illuminate\Support\Facades\DB::statement("
                    ALTER TABLE whatsapp_connections
                    MODIFY COLUMN status ENUM(
                        'PENDING',
                        'CONNECTING',
                        'QR_REQUIRED',
                        'CONNECTED',
                        'DISCONNECTED',
                        'RECONNECTING',
                        'ERROR'
                    ) NOT NULL DEFAULT 'PENDING'
                ");
            }

            // Baileys: ephemeral QR payload stored temporarily (never logged, never surfaced publicly)
            $table->text('baileys_qr_payload')->nullable()->after('settings');

            // Baileys: gateway phone number (the number Baileys is authenticated with)
            $table->string('baileys_phone_number', 30)->nullable()->after('baileys_qr_payload');

            // Baileys: when the QR was last generated (for expiry enforcement)
            $table->timestamp('qr_generated_at')->nullable()->after('baileys_phone_number');

            // Connection label distinguishing BAILEYS vs META for UI
            // Uses existing 'provider' column (already supports arbitrary string values)
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            $table->dropColumn(['baileys_qr_payload', 'baileys_phone_number', 'qr_generated_at']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            \Illuminate\Support\Facades\DB::statement("
                ALTER TABLE whatsapp_connections
                MODIFY COLUMN status ENUM('PENDING','CONNECTED','DISCONNECTED','ERROR') NOT NULL DEFAULT 'PENDING'
            ");
        }
    }
};
