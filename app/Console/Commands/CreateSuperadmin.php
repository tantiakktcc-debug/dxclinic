<?php

namespace App\Console\Commands;

use App\Models\DeveloperUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperadmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dxclinic:create-superadmin
                            {--name= : Superadmin full name}
                            {--email= : Superadmin email address}
                            {--password= : Password (prompts securely if omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update a platform superadmin account securely without hardcoded credentials';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Superadmin Name', 'Platform Superadmin');

        $email = $this->option('email');
        if (!$email) {
            $email = $this->ask('Superadmin Email Address');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address provided.');
            return self::FAILURE;
        }

        $password = $this->option('password');
        if (!$password) {
            $password = $this->secret('Enter Superadmin Password (minimum 8 characters)');
            $confirmPassword = $this->secret('Confirm Superadmin Password');

            if ($password !== $confirmPassword) {
                $this->error('Passwords do not match.');
                return self::FAILURE;
            }
        }

        if (strlen((string) $password) < 8) {
            $this->error('Password must be at least 8 characters in length.');
            return self::FAILURE;
        }

        $user = DeveloperUser::updateOrCreate(
            ['email' => strtolower(trim((string) $email))],
            [
                'name' => trim((string) $name),
                'password' => Hash::make((string) $password),
                'is_active' => true,
            ]
        );

        $this->info("Superadmin account successfully configured for: {$user->email}");
        $this->comment('Access route: /superadmin/login');

        return self::SUCCESS;
    }
}
