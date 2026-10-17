<?php

namespace Tests\Feature;

use App\Livewire\Operator\WhatsAppDesk;
use App\Models\Clinic;
use App\Models\License;
use App\Models\Patient;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Infrastructure\MockWhatsAppProvider;
use App\Modules\WhatsApp\Infrastructure\WhatsAppProviderInterface;
use App\Services\AppTimestampFormatter;
use App\Services\ClinicClock;
use App\Services\TenantContext;
use App\Services\WhatsAppTimestampNormalizer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppTimestampNormalizationTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected User $operator;
    protected WhatsAppConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(WhatsAppProviderInterface::class, new MockWhatsAppProvider());

        $plan = SubscriptionPlan::create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'price' => 1000,
            'duration_months' => 12,
            'max_doctors' => 5,
            'is_active' => true,
        ]);

        $this->clinic = Clinic::create([
            'clinic_code' => 'NX9999',
            'public_slug' => 'democlinic',
            'name' => 'Demo Clinic',
            'address' => 'Varanasi',
            'city' => 'Varanasi',
            'phone' => '9876543210',
            'activation_status' => 'ACTIVATED',
            'onboarding_status' => 'COMPLETED',
            'is_onboarded' => true,
        ]);

        License::create([
            'clinic_id' => $this->clinic->id,
            'plan_id' => $plan->id,
            'license_key_hash' => hash('sha256', 'TEST-KEY-1'),
            'license_key_encrypted' => encrypt('TEST-KEY-1'),
            'license_key_prefix' => 'NXCL',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $this->operator = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Frontdesk Operator',
            'email' => 'operator@democlinic.local',
            'role' => 'OPERATOR',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
            'must_change_password' => false,
        ]);

        $this->connection = WhatsAppConnection::create([
            'clinic_id' => $this->clinic->id,
            'provider' => 'baileys',
            'phone_number_id' => 'baileys_test_phone',
            'display_phone_number' => '+919876543210',
            'phone_number' => '919876543210',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);
    }

    /**
     * 1. UNIT TESTS: WhatsAppTimestampNormalizer
     */
    public function test_normalizer_handles_unix_seconds(): void
    {
        // 1791237888 = 2026-10-05 22:04:48 UTC
        $normalized = WhatsAppTimestampNormalizer::normalize(1791237888);

        $this->assertNotNull($normalized);
        $this->assertEquals(0, $normalized->offset);
        $this->assertEquals('2026-10-05 22:04:48', $normalized->format('Y-m-d H:i:s'));
    }

    public function test_normalizer_handles_unix_milliseconds(): void
    {
        // 1791237888000 = 2026-10-05 22:04:48 UTC in ms
        $normalized = WhatsAppTimestampNormalizer::normalize(1791237888000);

        $this->assertNotNull($normalized);
        $this->assertEquals(0, $normalized->offset);
        $this->assertEquals('2026-10-05 22:04:48', $normalized->format('Y-m-d H:i:s'));
    }

    public function test_normalizer_handles_iso_strings_and_offsets(): void
    {
        // ISO string in UTC
        $utcIso = WhatsAppTimestampNormalizer::normalize('2026-10-05T22:04:48Z');
        $this->assertNotNull($utcIso);
        $this->assertEquals('2026-10-05 22:04:48', $utcIso->format('Y-m-d H:i:s'));

        // ISO string with IST offset (+05:30)
        // 2026-10-06 03:34:48 +05:30 is 2026-10-05 22:04:48 UTC
        $istIso = WhatsAppTimestampNormalizer::normalize('2026-10-06T03:34:48+05:30');
        $this->assertNotNull($istIso);
        $this->assertEquals('2026-10-05 22:04:48', $istIso->format('Y-m-d H:i:s'));
    }

    public function test_normalizer_handles_protobuf_long_objects(): void
    {
        // Object with toNumber()
        $mockObj = new class {
            public function toNumber() { return 1791237888; }
        };
        $normalized = WhatsAppTimestampNormalizer::normalize($mockObj);
        $this->assertNotNull($normalized);
        $this->assertEquals('2026-10-05 22:04:48', $normalized->format('Y-m-d H:i:s'));

        // Array with low and high bits (Long serialization)
        // 1791237888 in hex is 0x6AC39B00 -> high = 0, low = 1791237888
        $longArr = ['high' => 0, 'low' => 1791237888];
        $normLong = WhatsAppTimestampNormalizer::normalize($longArr);
        $this->assertNotNull($normLong);
        $this->assertEquals('2026-10-05 22:04:48', $normLong->format('Y-m-d H:i:s'));
    }

    public function test_normalizer_handles_invalid_and_null_timestamps(): void
    {
        $this->assertNull(WhatsAppTimestampNormalizer::normalize(null));
        $this->assertNull(WhatsAppTimestampNormalizer::normalize(''));
        $this->assertNull(WhatsAppTimestampNormalizer::normalize(0));
        $this->assertNull(WhatsAppTimestampNormalizer::normalize(-100));
        $this->assertNull(WhatsAppTimestampNormalizer::normalize('invalid-date-string'));
    }

    /**
     * 2. UNIT TESTS: AppTimestampFormatter & IST Display
     */
    public function test_formatter_converts_utc_to_authoritative_ist(): void
    {
        // 14:24:00 IST is 08:54:00 UTC
        $utcCarbon = Carbon::createFromFormat('Y-m-d H:i:s', '2026-10-06 08:54:00', 'UTC');

        $this->assertEquals('02:24 PM', AppTimestampFormatter::formatTime($utcCarbon));
        $this->assertEquals('14:24', AppTimestampFormatter::format24HourTime($utcCarbon));
        $this->assertEquals('06 Oct 2026', AppTimestampFormatter::formatDate($utcCarbon));
        $this->assertEquals('06 Oct 2026, 02:24 PM', AppTimestampFormatter::formatDateTime($utcCarbon));
        $this->assertEquals('06 Oct 2026, 02:24 PM IST', AppTimestampFormatter::formatDateTimeWithTz($utcCarbon));
    }

    /**
     * 3. BOUNDARY TESTS: 00:00:00, 11:00:00, 19:00:00, 23:59:59 IST
     */
    public function test_boundary_times_ist(): void
    {
        // 00:00:00 IST on 2026-10-06 is 18:30:00 UTC on 2026-10-05
        $tMidnight = Carbon::createFromFormat('Y-m-d H:i:s', '2026-10-05 18:30:00', 'UTC');
        $this->assertEquals('12:00 AM', AppTimestampFormatter::formatTime($tMidnight));
        $this->assertEquals('06 Oct 2026', AppTimestampFormatter::formatDate($tMidnight));

        // 11:00:00 IST on 2026-10-06 is 05:30:00 UTC on 2026-10-06
        $t11am = Carbon::createFromFormat('Y-m-d H:i:s', '2026-10-06 05:30:00', 'UTC');
        $this->assertEquals('11:00 AM', AppTimestampFormatter::formatTime($t11am));
        $this->assertEquals('06 Oct 2026', AppTimestampFormatter::formatDate($t11am));

        // 19:00:00 IST on 2026-10-06 is 13:30:00 UTC on 2026-10-06
        $t7pm = Carbon::createFromFormat('Y-m-d H:i:s', '2026-10-06 13:30:00', 'UTC');
        $this->assertEquals('07:00 PM', AppTimestampFormatter::formatTime($t7pm));
        $this->assertEquals('06 Oct 2026', AppTimestampFormatter::formatDate($t7pm));

        // 23:59:59 IST on 2026-10-06 is 18:29:59 UTC on 2026-10-06
        $tEnd = Carbon::createFromFormat('Y-m-d H:i:s', '2026-10-06 18:29:59', 'UTC');
        $this->assertEquals('11:59 PM', AppTimestampFormatter::formatTime($tEnd));
        $this->assertEquals('06 Oct 2026', AppTimestampFormatter::formatDate($tEnd));
    }

    /**
     * 4. DATE ROLLOVER TESTS: UTC vs IST Date Differences
     */
    public function test_midnight_and_date_rollover_utc_vs_ist(): void
    {
        // 23:59:59 UTC on 2026-10-05 rolls over to 05:29:59 AM on 2026-10-06 in IST
        $utcBeforeMidnight = Carbon::createFromFormat('Y-m-d H:i:s', '2026-10-05 23:59:59', 'UTC');
        $this->assertEquals('06 Oct 2026', AppTimestampFormatter::formatDate($utcBeforeMidnight));
        $this->assertEquals('05:29 AM', AppTimestampFormatter::formatTime($utcBeforeMidnight));

        // 00:00:00 UTC on 2026-10-06 is 05:30:00 AM on 2026-10-06 in IST
        $utcMidnight = Carbon::createFromFormat('Y-m-d H:i:s', '2026-10-06 00:00:00', 'UTC');
        $this->assertEquals('06 Oct 2026', AppTimestampFormatter::formatDate($utcMidnight));
        $this->assertEquals('05:30 AM', AppTimestampFormatter::formatTime($utcMidnight));
    }

    /**
     * 5. HONEST DELIVERY STATUS FORMATTING
     */
    public function test_honest_delivery_status_formatting(): void
    {
        $utcTime = Carbon::createFromFormat('Y-m-d H:i:s', '2026-10-06 08:54:00', 'UTC'); // 02:24 PM IST

        // SENT with sent_at
        $this->assertEquals('Sent at 02:24 PM', AppTimestampFormatter::formatDeliveryStatus('SENT', null, $utcTime, null));

        // DELIVERED with delivered_at
        $this->assertEquals('Delivered at 02:24 PM', AppTimestampFormatter::formatDeliveryStatus('DELIVERED', $utcTime, null, null));

        // DELIVERED without delivered_at (honest state, not fabricated)
        $this->assertEquals('Delivered (time unavailable)', AppTimestampFormatter::formatDeliveryStatus('DELIVERED', null, null, null));

        // READ with read_at
        $this->assertEquals('Read at 02:24 PM', AppTimestampFormatter::formatDeliveryStatus('READ', null, null, $utcTime));

        // READ without read_at
        $this->assertEquals('Read', AppTimestampFormatter::formatDeliveryStatus('READ', null, null, null));

        // PENDING / FAILED
        $this->assertEquals('Pending', AppTimestampFormatter::formatDeliveryStatus('PENDING'));
        $this->assertEquals('Failed', AppTimestampFormatter::formatDeliveryStatus('FAILED'));
    }

    /**
     * 6. INTEGRATION TEST: WhatsApp Desk renders IST and distinct identities
     */
    public function test_whatsapp_desk_displays_ist_and_distinct_identities(): void
    {
        $this->actingAs($this->operator);
        TenantContext::setClinic($this->clinic);

        // 14:24:00 IST is 08:54:00 UTC
        $msgUtc = Carbon::createFromFormat('Y-m-d H:i:s', '2026-10-06 08:54:00', 'UTC');

        $conv = WhatsAppConversation::create([
            'clinic_id' => $this->clinic->id,
            'whatsapp_connection_id' => $this->connection->id,
            'wa_id' => '919876543299',
            'current_state' => 'MENU',
            'last_patient_message_at' => $msgUtc,
        ]);

        $inboundMsg = WhatsAppMessage::create([
            'clinic_id' => $this->clinic->id,
            'whatsapp_conversation_id' => $conv->id,
            'direction' => 'INBOUND',
            'message_type' => 'text',
            'provider_message_id' => 'msg_inbound_1',
            'content' => 'Test message from patient',
            'status' => 'DELIVERED',
            'created_at' => $msgUtc,
            'sent_at' => $msgUtc,
            'delivered_at' => $msgUtc,
        ]);

        $test = Livewire::test(WhatsAppDesk::class)
            ->call('selectConversation', $conv->id);

        // Header must clearly show:
        // 1. Authenticated Clinic Name
        $test->assertSee('Demo Clinic');
        // 2. Provider
        $test->assertSee('BAILEYS');
        // 3. Business WhatsApp Number
        $test->assertSee('+919876543210');
        // 4. Patient WhatsApp Number
        $test->assertSee('+919876543299');

        // Message bubble must display IST time (02:24 PM), NOT UTC (08:54)
        $test->assertSee('02:24 PM');
        $test->assertDontSee('08:54');
    }
}
