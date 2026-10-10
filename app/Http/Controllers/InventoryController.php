<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryItemRequest;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['search' => 'nullable|string|max:200', 'category' => 'nullable|string|max:40', 'page' => 'nullable|integer|min:1']);
        $query = InventoryItem::where('inventory', $request->attributes->get('inventory'));
        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('location', 'like', $term)->orWhere('qr_content', 'like', $term)->orWhere('details', 'like', $term));
        }
        if ($request->query('alert') === 'expiry') {
            $query->whereNotNull('expiration_date')->where('expiration_date', '<=', now()->addDays(90)->toDateString());
        }

        return InventoryItemResource::collection($query->orderBy('name')->paginate(24));
    }

    public function show(Request $request, int $item): InventoryItemResource
    {
        return new InventoryItemResource($this->find($request, $item));
    }

    public function store(InventoryItemRequest $request, InventoryService $service): JsonResponse
    {
        return response()->json($service->submit($request->user(), $request->attributes->get('inventory'), 'create', $request->validated()), 201);
    }

    public function update(InventoryItemRequest $request, int $item, InventoryService $service): JsonResponse
    {
        return response()->json($service->submit($request->user(), $request->attributes->get('inventory'), 'update', $request->validated(), $this->find($request, $item)));
    }

    public function destroy(Request $request, int $item, InventoryService $service): JsonResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        return response()->json($service->submit($request->user(), $request->attributes->get('inventory'), 'delete', $data, $this->find($request, $item)));
    }

    public function scan(Request $request): InventoryItemResource
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:2000']]);
        $inventory = $request->attributes->get('inventory');
        $code = trim($data['code']);
        $query = InventoryItem::where('inventory', $inventory);
        $item = (clone $query)->where('qr_content', $code)->first();
        if (! $item && preg_match('/^LK:([a-z]+):(\d+)$/', $code, $m)) {
            abort_unless($m[1] === $inventory, 404, 'Cambie al inventario indicado en el código.');
            $item = (clone $query)->find($m[2]);
        }
        if (! $item && preg_match('/^LAB-(SUB|SUBSTANCES|CHM|CHEM|CHEMICAL_MATERIALS|DID|DIDACTIC_MATERIALS|EQUIPO|EQUIPOS)-(\d+)$/i', $code, $m)) {
            $type = match (strtoupper($m[1])) {
                'SUB','SUBSTANCES' => 'substances','CHM','CHEM','CHEMICAL_MATERIALS' => 'chemical_materials','DID','DIDACTIC_MATERIALS' => 'didactic_materials',default => 'equipos'
            };
            $item = (clone $query)->where('category', $type)->where('legacy_id', $m[2])->first();
        }
        abort_unless($item, 404, 'Código no encontrado en este inventario.');

        return new InventoryItemResource($item);
    }

    private function find(Request $request,int $id): InventoryItem
    {
        return InventoryItem::where('inventory',$request->attributes->get('inventory'))->findOrFail($id);
    }
}
