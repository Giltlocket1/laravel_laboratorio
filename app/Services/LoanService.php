<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LoanService
{
    public function transition(User $user, Loan $loan, string $action, array $data): Loan
    {
        return DB::transaction(function () use ($user, $loan, $action, $data) {
            $loan = Loan::lockForUpdate()->findOrFail($loan->id);
            if ($action === 'request-return') {
                abort_unless($user->manages() || $loan->user_id === $user->id, 403);
                abort_unless($loan->status === 'approved', 409, 'El préstamo no está activo.');
                $photo = $data['return_photo'] ?? null;
                if ($photo) {
                    abort_unless(str_starts_with($photo, 'files/'.$loan->inventory.'/') && ! str_contains($photo, '..'), 422, 'Archivo no válido.');
                }
                $loan->return_photo = $photo;
                $next = 'return_pending';
            } else {
                abort_unless($user->manages(), 403);
                $expected = match ($action) {
                    'approve','reject' => 'pending','return','reject-return' => 'return_pending',default => ''
                };
                abort_unless($loan->status === $expected, 409, 'El préstamo ya cambió de estado.');
                $next = match ($action) {
                    'approve' => 'approved','reject' => 'rejected','return' => 'returned','reject-return' => 'approved'
                };
                $loan->reviewer_id = $user->id;
                if ($action === 'approve' || $action === 'return') {
                    $items = collect($loan->items)->sortBy('id');
                    foreach ($items as $line) {
                        $item = InventoryItem::where('inventory', $loan->inventory)->lockForUpdate()->findOrFail($line['id']);
                        if ($action === 'approve') {
                            abort_if((float) $item->quantity < $line['quantity'], 422, 'Existencias insuficientes: '.$item->name);
                        }
                        $before = $item->toArray();
                        $item->quantity = (float) $item->quantity + ($action === 'approve' ? -1 : 1) * $line['quantity'];
                        $item->version++;
                        $item->save();
                        AuditLog::record($user, $loan->inventory, $action === 'approve' ? 'Salida por préstamo' : 'Devolución', 'inventory_item', $item->id, $before, $item->toArray());
                    }
                    if ($action === 'approve') {
                        $loan->approved_at = now();
                    } else {
                        $loan->returned_at = now();
                    }
                }
            }
            $before = $loan->getOriginal('status');
            $loan->status = $next;
            $loan->feedback = $data['feedback'] ?? null;
            $loan->save();
            AuditLog::record($user, $loan->inventory, 'Préstamo: '.$action, 'loan', $loan->id, ['status' => $before], ['status' => $next]);

            return $loan;
        }, 3);
    }
}
