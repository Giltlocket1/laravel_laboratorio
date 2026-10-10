<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->active, 403, 'Su cuenta está desactivada.');
        if ($request->user()->must_change_password) {
            abort_unless(in_array($request->path(), ['api/v1/session', 'api/v1/password', 'api/v1/logout'], true), 423, 'Debe cambiar la contraseña temporal para continuar.');
        }
        if (in_array($request->path(), ['api/v1/session', 'api/v1/password', 'api/v1/logout'], true)) {
            return $next($request);
        }
        $inventory = (string) $request->header('X-Inventory-Id', $request->query('inventory', 'inventario'));
        abort_unless(array_key_exists($inventory, config('labkeep.inventories')), 422, 'Inventario no válido.');
        abort_unless(in_array($inventory, $request->user()->inventories(), true), 403, 'No tiene acceso a este inventario.');
        $request->attributes->set('inventory', $inventory);

        return $next($request);
    }
}
