<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CheckAdministrator extends Command
{
    protected $signature = 'labkeep:check-admin';

    protected $description = 'Comprobar si existe una cuenta administradora activa.';

    public function handle(): int
    {
        return User::where('role', 'admin')->where('active', true)->exists()
            ? self::SUCCESS
            : self::FAILURE;
    }
}
