<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ResetAdminPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nimbus:reset-admin-password 
                            {--email= : The email address of the Super Admin} 
                            {--password= : The new password (optional; prompted securely if omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely reset the password for a Nimbus Super Admin account via email verification';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email');

        if (empty($email)) {
            $email = $this->ask('Enter the Super Admin email address');
        }

        $email = trim((string) $email);

        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            $this->error('Validation error: ' . $validator->errors()->first('email'));
            return Command::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Error: No user found with email '{$email}'.");
            return Command::FAILURE;
        }

        // Strictly verify that the target account is a Super Admin (root role)
        if (!$user->isRoot()) {
            $this->error("Security Restriction: User '{$email}' exists but has role '{$user->role}'.");
            $this->error("This command is strictly restricted to Super Admin ('root') accounts.");
            return Command::FAILURE;
        }

        $password = $this->option('password');

        if (empty($password)) {
            $password = $this->secret('Enter new password (minimum 8 characters)');
            if (empty($password)) {
                $this->error('Password cannot be empty.');
                return Command::FAILURE;
            }

            $confirmation = $this->secret('Confirm new password');
            if ($password !== $confirmation) {
                $this->error('Passwords do not match.');
                return Command::FAILURE;
            }
        }

        if (strlen((string) $password) < 8) {
            $this->error('Password must be at least 8 characters long.');
            return Command::FAILURE;
        }

        $user->password = Hash::make($password);
        $user->remember_token = Str::random(60);
        $user->save();

        $this->info("--------------------------------------------------");
        $this->info(" Super Admin Password Reset Successfully");
        $this->info("--------------------------------------------------");
        $this->line(" Name  : {$user->name}");
        $this->line(" Email : {$user->email}");
        $this->line(" Role  : {$user->role}");
        $this->info("You may now log in to the Nimbus panel with your new password.");

        return Command::SUCCESS;
    }
}
