<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'inventory' => $this->inventory, 'category' => $this->category, 'legacy_id' => $this->legacy_id, 'name' => $this->name, 'quantity' => (float) $this->quantity, 'unit' => $this->unit, 'location' => $this->location, 'expiration_date' => $this->expiration_date?->format('Y-m-d'), 'qr_content' => $this->qr_content, 'details' => $this->details ?? [], 'version' => $this->version, 'updated_at' => $this->updated_at];
    }
}
