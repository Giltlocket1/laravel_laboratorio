<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Loan::where('inventory', $request->attributes->get('inventory'));
        if (! $request->user()->manages()) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->latest()->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['items' => ['required', 'array', 'min:1', 'max:50'], 'items.*.id' => ['required', 'integer', 'distinct'], 'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'], 'notes' => ['nullable', 'string', 'max:5000']]);
        $inventory = $request->attributes->get('inventory');
        $loan = DB::transaction(function () use ($request, $data, $inventory) {
            $lines = [];
            foreach ($data['items'] as $line) {
                $item = InventoryItem::where('inventory', $inventory)->findOrFail($line['id']);
                abort_if($line['quantity'] > (float) $item->quantity, 422, 'Existencias insuficientes: '.$item->name);
                $lines[] = ['id' => $item->id, 'name' => $item->name, 'quantity' => (float) $line['quantity'], 'unit' => $item->unit];
            }
            $loan = Loan::create(['inventory' => $inventory, 'user_id' => $request->user()->id, 'borrower_name' => $request->user()->name, 'items' => $lines, 'notes' => $data['notes'] ?? null]);
            AuditLog::record($request->user(), $inventory, 'Solicitud de préstamo', 'loan', $loan->id, [], ['items' => $lines]);

            return $loan;
        });

        return response()->json(['data' => $loan], 201);
    }

    public function update(Request $request, int $loan, string $action, LoanService $service): JsonResponse
    {
        $data = $request->validate(['feedback' => [in_array($action, ['reject', 'reject-return']) ? 'required' : 'nullable', 'string', 'max:5000'], 'return_photo' => ['nullable', 'string', 'max:255']]);
        $record = Loan::where('inventory', $request->attributes->get('inventory'))->findOrFail($loan);

        return response()->json(['data' => $service->transition($request->user(), $record, $action, $data)]);
    }
}
