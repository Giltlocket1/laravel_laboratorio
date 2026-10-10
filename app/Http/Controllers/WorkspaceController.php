<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Models\InventoryItem;
use App\Models\LegacyRecord;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $inventory = $request->attributes->get('inventory');
        $items = InventoryItem::where('inventory', $inventory);
        $loans = Loan::where('inventory', $inventory);
        $changes = ChangeRequest::where('inventory', $inventory);
        if (! $request->user()->manages()) {
            $loans->where('user_id', $request->user()->id);
            $changes->where('user_id', $request->user()->id);
        }

        return response()->json([
            'counts' => (clone $items)->selectRaw('category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category'),
            'expiring' => (clone $items)->whereNotNull('expiration_date')->where('expiration_date', '<=', now()->addDays(90)->toDateString())->orderBy('expiration_date')->limit(6)->get(),
            'expiry_count' => (clone $items)->whereNotNull('expiration_date')->where('expiration_date', '<=', now()->addDays(90)->toDateString())->count(),
            'active_loans' => (clone $loans)->whereIn('status', ['approved', 'return_pending'])->count(),
            'pending_changes' => $changes->where('status', 'pending')->count(),
            'recent' => AuditLog::where('inventory', $inventory)->latest()->limit(5)->get(['id', 'actor', 'action', 'entity', 'entity_id', 'created_at']),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless($request->user()->manages(), 403);
        $inventory = $request->attributes->get('inventory');
        if ($request->query('legacy') === '1') {
            return response()->json(LegacyRecord::where('inventory', $inventory)->where('source_table', 'change_history')->orderByDesc('id')->paginate(30));
        }

        return response()->json(AuditLog::where('inventory', $inventory)->latest()->paginate(30));
    }

    public function archive(Request $request): JsonResponse
    {
        abort_unless($request->user()->manages(), 403);
        $table = $request->validate(['table' => ['required', 'in:loans,change_requests,control_mayor_records,device_approvals']])['table'];

        return response()->json(LegacyRecord::where('inventory', $request->attributes->get('inventory'))->where('source_table', $table)->orderByDesc('id')->paginate(25));
    }

    public function reference(Request $request): JsonResponse
    {
        return response()->json(['data' => LegacyRecord::where('inventory', $request->attributes->get('inventory'))->where('source_table', 'like', 'consulta_%')->orderBy('source_table')->get()]);
    }

    public function check(Request $request): JsonResponse
    {
        $data = $request->validate(['found' => ['required', 'array', 'min:1', 'max:5000'], 'found.*' => ['integer', 'distinct'], 'notes' => ['nullable', 'string', 'max:5000']]);
        $inventory = $request->attributes->get('inventory');
        $ids = InventoryItem::where('inventory', $inventory)->whereIn('id', $data['found'])->pluck('id');
        abort_unless($ids->count() === count($data['found']), 422, 'La verificación contiene registros de otro inventario.');
        AuditLog::record($request->user(), $inventory, 'Verificación física', 'inventory_check', null, [], ['found' => $ids->all(), 'notes' => $data['notes'] ?? '']);

        return response()->json(['message' => 'Verificación guardada.', 'count' => $ids->count()]);
    }

    public function export(Request $request): StreamedResponse
    {
        $inventory = $request->attributes->get('inventory');

        return response()->streamDownload(function () use ($inventory) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Tipo', 'Nombre', 'Disponible', 'Unidad', 'Ubicación', 'Caducidad', 'Datos técnicos'], ',', '"', '');
            foreach (InventoryItem::where('inventory', $inventory)->orderBy('id')->cursor() as $item) {
                $row = [$item->id, $item->category, $item->name, $item->quantity, $item->unit, $item->location, $item->expiration_date?->format('Y-m-d'), json_encode($item->details, JSON_UNESCAPED_UNICODE)];
                $row = array_map(fn ($v) => preg_match('/^[\s]*[=+\-@]/', (string) $v) ? "'".$v : $v, $row);
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, 'labkeep-'.$inventory.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
