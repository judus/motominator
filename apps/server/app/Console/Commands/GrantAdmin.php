<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:grant-admin {email : Email address of an existing user}')]
#[Description('Explicitly grant administrator access to an existing account')]
class GrantAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('Account not found. Register an account or create one with make:filament-user first.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->info('Administrator access granted to '.$user->email.'.');

        return self::SUCCESS;
    }
}
