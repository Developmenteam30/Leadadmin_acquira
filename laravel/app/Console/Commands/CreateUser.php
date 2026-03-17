<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create {username} {password} {--name=} {--email=} {--access-bits=256}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user in the system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $username = $this->argument('username');
        $password = $this->argument('password');
        $fullName = $this->option('name') ?: $username;
        $email = $this->option('email') ?: $username;
        $accessBits = (int) $this->option('access-bits');

        // Check if user already exists
        $existingUser = User::where('username', $username)->first();
        if ($existingUser) {
            $this->error("User with username '{$username}' already exists!");
            return 1;
        }

        // Hash password using PHP password_hash (for compatibility with existing system)
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => 11]);

        // Create user
        $user = User::create([
            'username' => $username,
            'fullName' => $fullName,
            'password' => $hashedPassword,
            'email' => $email,
            'accessBits' => $accessBits,
            'idCompany' => null,
            'level' => 0,
            'isArchived' => 0,
            'emailBits' => 0,
        ]);

        $this->info("User '{$username}' created successfully!");
        $this->info("ID: {$user->idUser}");
        $this->info("Access Bits: {$accessBits} (Admin)");

        return 0;
    }
}
