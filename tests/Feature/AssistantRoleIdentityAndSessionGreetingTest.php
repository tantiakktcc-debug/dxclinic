<?php

namespace Tests\Feature;

use App\Livewire\Operator\OperatorAssistant;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use App\Services\AssistantIdentityResolver;
use App\Services\ClinicClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssistantRoleIdentityAndSessionGreetingTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected User $adminUser;
    protected User $operatorUser;
    protected User $doctorUser;
    protected User $superadminUser;
    protected Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create([
            'clinic_code' => 'NX9999',
            'public_slug' => 'testclinic',
            'name' => 'Apex Test Clinic',
            'phone' => '9999999999',
            'email' => 'apex@test.com',
            'address' => 'Mall Road',
            'city' => 'Kanpur',
            'is_active' => true,
        ]);

        $this->doctor = Doctor::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Dr. Amit Verma',
            'qualification' => 'MBBS, MD',
            'registration_number' => 'MCI-998877',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Demo Administrator',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'ADMIN',
        ]);

        $this->operatorUser = User::create([
            'name' => 'Reception Operator',
            'email' => 'operator@test.com',
            'password' => bcrypt('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'OPERATOR',
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dr. Amit Verma',
            'email' => 'doctor@test.com',
            'password' => bcrypt('password'),
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'role' => 'DOCTOR',
        ]);

        $this->superadminUser = User::create([
            'name' => 'Platform Superadmin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'SUPERADMIN',
        ]);
    }

    public function test_admin_assistant_identity_resolution(): void
    {
        $resolver = app(AssistantIdentityResolver::class);
        $identity = $resolver->resolve($this->adminUser, $this->clinic);

        $this->assertEquals('ADMIN', $identity['role']);
        $this->assertFalse($identity['is_doctor']);
        $this->assertEquals('Admin Assistant', $identity['short_label']);
        $this->assertEquals('DxClinic Admin Assistant', $identity['full_title']);
        $this->assertEquals('Hello, Demo Administrator. How can I help you?', $identity['greeting_text']);
    }

    public function test_operator_assistant_identity_resolution(): void
    {
        $resolver = app(AssistantIdentityResolver::class);
        $identity = $resolver->resolve($this->operatorUser, $this->clinic);

        $this->assertEquals('OPERATOR', $identity['role']);
        $this->assertFalse($identity['is_doctor']);
        $this->assertEquals('Operator Assistant', $identity['short_label']);
        $this->assertEquals('DxClinic Operator Assistant', $identity['full_title']);
        $this->assertEquals('Hello, Reception Operator. How can I help you?', $identity['greeting_text']);
    }

    public function test_doctor_assistant_identity_resolution_and_name_normalization(): void
    {
        $resolver = app(AssistantIdentityResolver::class);
        $identity = $resolver->resolve($this->doctorUser, $this->clinic);

        $this->assertEquals('DOCTOR', $identity['role']);
        $this->assertTrue($identity['is_doctor']);
        $this->assertEquals('Doctor Assistant', $identity['short_label']);
        // Ensures no "Dr. Dr. Amit Verma" duplicate title
        $this->assertEquals("DxClinic Dr. Amit Verma's Assistant", $identity['full_title']);
        $this->assertEquals('Hello, Dr. Amit Verma. How can I help you?', $identity['greeting_text']);
    }

    public function test_superadmin_assistant_identity_resolution(): void
    {
        $resolver = app(AssistantIdentityResolver::class);
        $identity = $resolver->resolve($this->superadminUser, $this->clinic);

        $this->assertEquals('SUPERADMIN', $identity['role']);
        $this->assertEquals('Superadmin Assistant', $identity['short_label']);
        $this->assertEquals('DxClinic Superadmin Assistant', $identity['full_title']);
        $this->assertEquals('Hello, Platform Superadmin. How can I help you?', $identity['greeting_text']);
    }

    public function test_greeting_is_generated_once_per_operational_session(): void
    {
        $this->actingAs($this->doctorUser);
        session()->flush();

        // 1. First mount in session: greeting generated
        $component = Livewire::test(OperatorAssistant::class);
        $component->call('toggleChat');
        $component->assertSee("Hello, Dr. Amit Verma. How can I help you?");
        $component->assertSee("Doctor Assistant");
        $component->assertSee("DxClinic Dr. Amit Verma&#039;s Assistant", false);

        $conv = $component->get('conversation');
        $this->assertCount(1, $conv);
        $this->assertStringContainsString("Hello, Dr. Amit Verma. How can I help you?", $conv[0]['text']);

        // 2. Second mount in same session (e.g. page navigation): greeting NOT duplicated
        $component2 = Livewire::test(OperatorAssistant::class);
        $conv2 = $component2->get('conversation');
        $this->assertCount(1, $conv2);

        // 3. User sends a query
        $component2->set('userMessage', 'Who is default doctor?');
        $component2->call('sendMessage', app(\App\Services\RagAssistantService::class));
        $this->assertCount(3, $component2->get('conversation')); // greeting + user message + assistant reply

        // 4. Third mount: loads same 3 messages, no extra greeting
        $component3 = Livewire::test(OperatorAssistant::class);
        $this->assertCount(3, $component3->get('conversation'));
    }

    public function test_new_operational_session_receives_new_greeting(): void
    {
        $this->actingAs($this->adminUser);
        session()->flush();

        // Mount in morning session
        $component = Livewire::test(OperatorAssistant::class);
        $sessionKey1 = $component->get('identity')['session_key'];
        $this->assertNotEmpty($sessionKey1);
        $this->assertTrue(session()->get("assistant_greeted_{$sessionKey1}"));

        // Simulate new session by clearing that specific session key or changing session
        $newSessionKey = "clinic_{$this->clinic->id}_user_{$this->adminUser->id}_2026-10-04_EVENING";
        $this->assertFalse(session()->get("assistant_greeted_{$newSessionKey}", false));
    }
}
