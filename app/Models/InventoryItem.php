<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['inventory', 'category', 'legacy_id', 'name', 'quantity', 'unit', 'location', 'expiration_date', 'qr_content', 'details', 'version'];

    protected function casts(): array
    {
        return ['details' => 'array', 'quantity' => 'decimal:3', 'expiration_date' => 'date', 'version' => 'integer'];
    }
}
