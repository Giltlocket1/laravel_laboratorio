<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = ['inventory', 'user_id', 'inventory_item_id', 'action', 'payload', 'base_version', 'status', 'feedback', 'reviewer_id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'base_version' => 'integer'];
    }
}
