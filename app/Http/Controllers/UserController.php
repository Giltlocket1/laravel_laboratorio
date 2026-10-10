<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        return response()->json(['data' => User::orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $this->validated($request);
        $user = User::create($data);
        AuditLog::record($request->user(), $request->attributes->get('inventory'), 'Alta de usuario', 'user', $user->id, [], ['username' => $user->username, 'role' => $user->role]);

        return response()->json(['data' => $user], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $this->validated($request, $user);
        DB::transaction(function () use ($request, $user, $data) {
            User::where('role', 'admin')->lockForUpdate()->get();
            abort_if($user->id === $request->user()->id && (! $data['active'] || $data['role'] !== 'admin'), 422, 'No puede desactivar ni degradar su propia cuenta.');
            $before = $user->only(['role', 'active', 'allowed_inventories']);
            $user->update($data);
            AuditLog::record($request->user(), $request->attributes->get('inventory'), 'Cambio de usuario', 'user', $user->id, $before, $user->only(['role', 'active', 'allowed_inventories']));
        });

        return response()->json(['data' => $user]);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'], 'username' => ['required', 'alpha_dash', 'max:100', Rule::unique('users')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'sometimes' : 'required', 'string', 'min:12', 'max:1024'],
            'role' => ['required', 'in:admin,jefe,responsable'], 'active' => ['required', 'boolean'],
            'allowed_inventories' => ['required', 'array', 'min:1'], 'allowed_inventories.*' => [Rule::in(array_keys(config('labkeep.inventories')))],
        ]);
    }
}
