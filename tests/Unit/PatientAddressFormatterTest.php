<?php

namespace Tests\Unit;

use App\Models\Patient;
use App\Services\PatientAddressFormatter;
use PHPUnit\Framework\TestCase;

class PatientAddressFormatterTest extends TestCase
{
    private PatientAddressFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new PatientAddressFormatter();
    }

    public function test_formats_address_and_city_cleanly(): void
    {
        $result = $this->formatter->format('Flat 402, Ganga Heights, Assi Ghat', 'Varanasi');
        $this->assertEquals('Flat 402, Ganga Heights, Assi Ghat, Varanasi', $result);
        $this->assertStringNotContainsString('City:', $result);
    }

    public function test_formats_only_city(): void
    {
        $result = $this->formatter->format(null, 'Varanasi');
        $this->assertEquals('Varanasi', $result);
        $this->assertStringNotContainsString('City:', $result);
    }

    public function test_formats_only_address(): void
    {
        $result = $this->formatter->format('Godowlia Chowk', null);
        $this->assertEquals('Godowlia Chowk', $result);
        $this->assertStringNotContainsString('City:', $result);
    }

    public function test_handles_empty_or_whitespace_values(): void
    {
        $result = $this->formatter->format('   ', '');
        $this->assertEquals('', $result);
    }

    public function test_trims_and_cleans_duplicate_commas(): void
    {
        $result = $this->formatter->format('Lanka, BHU Gate, ', 'Varanasi');
        $this->assertEquals('Lanka, BHU Gate, Varanasi', $result);
    }
}
