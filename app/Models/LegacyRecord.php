<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegacyRecord extends Model
{
    use HasFactory;

    protected $fillable = ['inventory', 'source_table', 'source_id', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
