<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = ['inventory', 'user_id', 'borrower_name', 'items', 'status', 'notes', 'feedback', 'return_photo', 'approved_at', 'returned_at', 'reviewer_id'];

    protected function casts(): array
    {
        return ['items' => 'array', 'approved_at' => 'datetime', 'returned_at' => 'datetime'];
    }
}
