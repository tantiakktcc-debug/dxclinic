<?php

namespace Tests\Feature;

use App\Models\DeveloperUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSuperadminCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\App\Console\Commands\CreateSuperadmin::class)) {
            require_once __DIR__ . '/../../app/Console/Commands/CreateSuperadmin.php';
        }
        $this->app[\Illuminate\Contracts\Console\Kernel::class]->registerCommand(new \App\Console\Commands\CreateSuperadmin);
    }

    public function test_can_create_superadmin_via_artisan_options(): void
    {
        $this->artisan('dxclinic:create-superadmin', [
            '--name' => 'Root Administrator',
            '--email' => 'admin@example.local',
            '--password' => 'StrongTestPass2026!',
        ])
            ->expectsOutputToContain('Superadmin account successfully configured for: admin@example.local')
            ->assertSuccessful();

        $user = DeveloperUser::where('email', 'admin@example.local')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Root Administrator', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('StrongTestPass2026!', $user->password));
        $this->assertNotEquals('StrongTestPass2026!', $user->password);
    }

    public function test_rejects_invalid_email_format(): void
    {
        $this->artisan('dxclinic:create-superadmin', [
            '--name' => 'Bad Email Admin',
            '--email' => 'not-a-valid-email',
            '--password' => 'StrongTestPass2026!',
        ])
            ->expectsOutput('Invalid email address provided.')
            ->assertFailed();

        $this->assertNull(DeveloperUser::where('name', 'Bad Email Admin')->first());
    }

    public function test_rejects_short_password(): void
    {
        $this->artisan('dxclinic:create-superadmin', [
            '--name' => 'Short Pass Admin',
            '--email' => 'short@example.local',
            '--password' => 'short',
        ])
            ->expectsOutput('Password must be at least 8 characters in length.')
            ->assertFailed();

        $this->assertNull(DeveloperUser::where('email', 'short@example.local')->first());
    }

    public function test_updates_existing_superadmin_idempotently(): void
    {
        DeveloperUser::create([
            'name' => 'Initial Name',
            'email' => 'existing@example.local',
            'password' => Hash::make('OldPassword123!'),
            'is_active' => true,
        ]);

        $this->artisan('dxclinic:create-superadmin', [
            '--name' => 'Updated Name',
            '--email' => 'existing@example.local',
            '--password' => 'NewPassword456!',
        ])->assertSuccessful();

        $user = DeveloperUser::where('email', 'existing@example.local')->first();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertTrue(Hash::check('NewPassword456!', $user->password));
    }
}
