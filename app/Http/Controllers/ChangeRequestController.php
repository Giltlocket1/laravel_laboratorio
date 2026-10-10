<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChangeRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ChangeRequest::where('inventory', $request->attributes->get('inventory'));
        if (! $request->user()->manages()) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->latest()->paginate(25));
    }

    public function update(Request $request, int $change, InventoryService $service): JsonResponse
    {
        abort_unless($request->user()->manages(), 403);
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'feedback' => ['nullable', 'string', 'max:5000']]);
        DB::transaction(function () use ($request, $change, $data, $service) {
            $record = ChangeRequest::where('inventory', $request->attributes->get('inventory'))->lockForUpdate()->findOrFail($change);
            abort_unless($record->status === 'pending', 409, 'La solicitud ya fue revisada.');
            if ($data['decision'] === 'approved') {
                $item = $record->inventory_item_id ? InventoryItem::where('inventory', $record->inventory)->lockForUpdate()->findOrFail($record->inventory_item_id) : null;
                if ($item) {
                    abort_unless($item->version === $record->base_version, 409, 'El registro cambió desde esta solicitud; solicite una nueva revisión.');
                }
                $service->apply($request->user(), $record->inventory, $record->action, $record->payload, $item);
            }
            $record->update(['status' => $data['decision'], 'feedback' => $data['feedback'] ?? null, 'reviewer_id' => $request->user()->id]);
            AuditLog::record($request->user(), $record->inventory, 'Revisión de cambio', 'change_request', $record->id, [], ['status' => $data['decision']]);
        }, 3);

        return response()->json(['message' => 'Solicitud revisada.']);
    }
}
