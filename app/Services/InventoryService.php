<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Models\InventoryItem;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function submit(User $user, string $inventory, string $action, array $data, ?InventoryItem $item = null): array
    {
        return DB::transaction(function () use ($user, $inventory, $action, $data, $item) {
            if ($item) {
                $item = InventoryItem::where('inventory', $inventory)->lockForUpdate()->findOrFail($item->id);
                abort_unless(($data['version'] ?? 0) === $item->version, 409, 'El registro cambió. Actualice la ficha antes de guardar.');
                abort_unless(($data['category'] ?? $item->category) === $item->category, 422, 'No se puede cambiar el tipo de un registro existente.');
            }
            if (! $user->manages()) {
                $change = ChangeRequest::create(['inventory' => $inventory, 'user_id' => $user->id, 'inventory_item_id' => $item?->id, 'action' => $action, 'payload' => $data, 'base_version' => $item?->version]);
                AuditLog::record($user, $inventory, 'Solicitud de cambio', 'change_request', $change->id, [], ['action' => $action]);

                return ['pending' => true, 'message' => 'Solicitud enviada para aprobación.'];
            }

            return ['pending' => false, 'item' => $this->apply($user, $inventory, $action, $data, $item)];
        }, 3);
    }

    public function apply(User $user, string $inventory, string $action, array $data, ?InventoryItem $item): ?InventoryItem
    {
        $before = $item?->toArray() ?? [];
        if ($action === 'delete') {
            abort_unless($item, 404);
            $hasLoan = Loan::where('inventory', $inventory)->whereIn('status', ['approved', 'return_pending'])->get(['items'])
                ->contains(fn ($loan) => collect($loan->items)->contains('id', $item->id));
            abort_if($hasLoan, 409, 'No se puede eliminar un artículo con préstamo activo.');
            $item->delete();
            AuditLog::record($user, $inventory, 'Eliminación', 'inventory_item', $item->id, $before, []);

            return null;
        }
        $details = array_replace($item?->details ?? [], $data['details'] ?? []);
        foreach (['image_path', 'pdf_path'] as $field) {
            if (! empty($data['details'][$field])) {
                abort_unless(str_starts_with($data['details'][$field], 'files/'.$inventory.'/') && ! str_contains($data['details'][$field], '..'), 422, 'Archivo de otro inventario.');
            }
        }
        $values = Arr::only($data, ['name', 'category', 'quantity', 'unit', 'location', 'expiration_date']);
        $values['details'] = $details;
        if ($item) {
            $item->fill($values);
            $item->version++;
            $item->save();
        } else {
            $item = InventoryItem::create([...$values, 'inventory' => $inventory]);
            $item->update(['qr_content' => 'LK:'.$inventory.':'.$item->id]);
        }
        AuditLog::record($user,$inventory,$action === 'create' ? 'Registro' : 'Actualización','inventory_item',$item->id,$before,$item->toArray());

        return $item;
    }
}
