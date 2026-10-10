<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = ['inventory', 'user_id', 'actor', 'action', 'entity', 'entity_id', 'before', 'after'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public static function record(User $user, string $inventory, string $action, string $entity, ?int $id, array $before = [], array $after = []): void
    {
        self::create(['inventory' => $inventory, 'user_id' => $user->id, 'actor' => $user->name, 'action' => $action, 'entity' => $entity, 'entity_id' => $id, 'before' => $before, 'after' => $after]);
    }
}
