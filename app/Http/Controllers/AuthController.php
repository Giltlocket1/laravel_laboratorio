<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['username' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'max:1024']]);
        if (! Auth::attempt([...$data, 'active' => true])) {
            throw ValidationException::withMessages(['username' => 'Usuario o contraseña incorrectos.']);
        }
        $request->session()->regenerate();

        return response()->json(['user' => $request->user(), 'csrf_token' => csrf_token()]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user(), 'csrf_token' => csrf_token(), 'inventories' => config('labkeep.inventories'), 'categories' => config('labkeep.categories')]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada.', 'csrf_token' => csrf_token()]);
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'max:1024', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ], [
            'current_password.required' => 'Escribe la contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no coincide.',
            'password.required' => 'Escribe una contraseña nueva.',
            'password.max' => 'La contraseña nueva es demasiado larga.',
            'password.confirmed' => 'La confirmación no coincide con la contraseña nueva.',
            'password.min' => 'La contraseña nueva debe tener al menos 12 caracteres.',
            'password.password.letters' => 'La contraseña nueva debe incluir al menos una letra.',
            'password.password.mixed' => 'La contraseña nueva debe incluir mayúsculas y minúsculas.',
            'password.password.numbers' => 'La contraseña nueva debe incluir al menos un número.',
            'password.password.symbols' => 'La contraseña nueva debe incluir al menos un símbolo, por ejemplo ! o #.',
        ]);
        $user = $request->user();
        $user->update(['password' => $data['password'], 'must_change_password' => false]);
        $request->session()->regenerate();

        return response()->json(['message' => 'Contraseña actualizada.', 'user' => $user->fresh()]);
    }
}
