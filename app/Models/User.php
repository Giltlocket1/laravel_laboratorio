<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'active', 'allowed_inventories', 'must_change_password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'active' => 'boolean', 'allowed_inventories' => 'array', 'must_change_password' => 'boolean'];
    }

    public function manages(): bool
    {
        return $this->active && in_array($this->role, ['admin', 'jefe'], true);
    }

    public function inventories(): array
    {
        return $this->role === 'admin' ? array_keys(config('labkeep.inventories')) : ($this->allowed_inventories ?? []);
    }
}
