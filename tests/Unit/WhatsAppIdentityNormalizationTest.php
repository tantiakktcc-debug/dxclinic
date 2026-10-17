<?php

namespace Tests\Unit;

use App\Services\WhatsAppIdentityNormalizer;
use PHPUnit\Framework\TestCase;

class WhatsAppIdentityNormalizationTest extends TestCase
{
    public function test_normalizes_international_format(): void
    {
        $this->assertEquals('919123456789', WhatsAppIdentityNormalizer::normalize('+91 9123456789'));
        $this->assertEquals('919123456789', WhatsAppIdentityNormalizer::normalize('+91-9123-456-789'));
        $this->assertEquals('919123456789', WhatsAppIdentityNormalizer::normalize('919123456789'));
    }

    public function test_normalizes_whatsapp_jid(): void
    {
        $this->assertEquals('919123456789', WhatsAppIdentityNormalizer::normalize('919123456789@s.whatsapp.net'));
        $this->assertEquals('919123456789', WhatsAppIdentityNormalizer::normalize('919123456789:2@s.whatsapp.net'));
    }

    public function test_normalizes_10_digit_indian_number(): void
    {
        $this->assertEquals('919123456789', WhatsAppIdentityNormalizer::normalize('9123456789'));
        $this->assertEquals('919123456789', WhatsAppIdentityNormalizer::normalize('09123456789'));
    }

    public function test_converts_to_jid(): void
    {
        $this->assertEquals('919123456789@s.whatsapp.net', WhatsAppIdentityNormalizer::toJid('+91 9123456789'));
    }

    public function test_converts_to_10_digit(): void
    {
        $this->assertEquals('9123456789', WhatsAppIdentityNormalizer::to10Digit('+91 9123456789'));
        $this->assertEquals('9123456789', WhatsAppIdentityNormalizer::to10Digit('919123456789@s.whatsapp.net'));
    }

    public function test_separates_business_and_patient_identity(): void
    {
        $businessLine = '+91 9876543210';
        $patientLine = '+91 9123456789';

        $this->assertTrue(WhatsAppIdentityNormalizer::isBusinessNumber($businessLine, '+91 9876543210'));
        $this->assertFalse(WhatsAppIdentityNormalizer::isBusinessNumber($patientLine, '+91 9876543210'));

        $this->assertTrue(WhatsAppIdentityNormalizer::isPatientNumber($patientLine, '+91 9876543210'));
        $this->assertFalse(WhatsAppIdentityNormalizer::isPatientNumber($businessLine, '+91 9876543210'));
    }
}
