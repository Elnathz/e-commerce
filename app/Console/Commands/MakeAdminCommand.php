<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeAdminCommand extends Command
{
    protected $signature = 'ecommerce:make-admin {email?}';
    protected $description = 'Create a new admin user or promote an existing user to admin';

    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Enter admin email address');

        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            $this->error('Invalid email address: ' . $email);
            return self::FAILURE;
        }

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            if ($existingUser->role === 'admin') {
                $this->warn("User {$email} is already an admin.");
                return self::SUCCESS;
            }

            if ($this->confirm("User {$email} exists as '{$existingUser->role}'. Promote to admin?")) {
                $existingUser->update(['role' => 'admin']);
                $this->info("✅ User {$email} has been promoted to admin.");
                return self::SUCCESS;
            }

            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        // Create new admin user
        $name = $this->ask('Enter admin name');
        $password = $this->secret('Enter password (min 8 characters)');

        $validator = Validator::make([
            'name' => $name,
            'password' => $password,
        ], [
            'name' => 'required|string|min:2|max:255',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->info("✅ Admin user '{$name}' ({$email}) created successfully.");
        return self::SUCCESS;
    }
}
