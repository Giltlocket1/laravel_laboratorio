<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdministrator extends Command
{
    protected $signature = 'labkeep:admin {username} {--email=} {--reset} {--preview}';

    protected $description = 'Crear administrador o restablecer su contraseña.';

    public function handle(): int
    {
        $username = $this->argument('username');
        if (! preg_match('/^[a-zA-Z0-9_-]{3,100}$/', $username)) {
            $this->error('Nombre de usuario no válido.');

            return self::FAILURE;
        }
        $user = User::where('username', $username)->first();
        if ($user && ! $this->option('reset')) {
            $this->error('La cuenta existe. Use --reset para restablecerla.');

            return self::FAILURE;
        }
        if ($this->option('preview') && ! app()->environment('local', 'testing')) {
            $this->error('Vista previa sólo disponible en entorno local.');

            return self::FAILURE;
        }
        $password = $this->option('preview') ? Str::password(24) : $this->secret('Contraseña nueva (mínimo 12 caracteres)');
        if (! is_string($password) || strlen($password) < 12) {
            $this->error('Contraseña demasiado corta.');

            return self::FAILURE;
        }
        $email = $this->option('email') ?? $user?->email;
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Especifique un correo válido con --email.');

            return self::FAILURE;
        }
        $data = ['username' => $username, 'name' => $user?->name ?? $username, 'email' => $email, 'password' => $password, 'role' => 'admin', 'active' => true, 'must_change_password' => false, 'allowed_inventories' => array_keys(config('labkeep.inventories'))];
        if ($user) {
            $user->update($data);
        } else {
            User::create($data);
        }
        $this->info('Administrador configurado.');
        if ($this->option('preview')) {
            $this->line($password);
        }

        return self::SUCCESS;
    }
}
