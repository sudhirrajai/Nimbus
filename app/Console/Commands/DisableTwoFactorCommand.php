<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class DisableTwoFactorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nimbus:2fa-disable 
                            {--email= : The email address of the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emergency command to disable two-factor authentication for a user';

    /**
     * Execute the console command.
     */
    public function handle(TwoFactorService $twoFactorService): int
    {
        $email = $this->option('email');

        if (empty($email)) {
            $email = $this->ask('Enter the user email address');
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

        if (!$user->hasTwoFactorEnabled()) {
            $this->warn("Two-factor authentication is already disabled for '{$email}'.");
            return Command::SUCCESS;
        }

        $twoFactorService->disableTwoFactor($user);

        $this->info("--------------------------------------------------");
        $this->info(" Two-Factor Authentication Disabled Successfully");
        $this->info("--------------------------------------------------");
        $this->line(" Name  : {$user->name}");
        $this->line(" Email : {$user->email}");
        $this->line(" Role  : {$user->role}");
        $this->info("The user may now sign in using only their email and password.");

        return Command::SUCCESS;
    }
}
