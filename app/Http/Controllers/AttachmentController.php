<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240']]);
        $path = $request->file('file')->store('attachments/'.$request->attributes->get('inventory'), 'local');

        return response()->json(['path' => 'files/'.substr($path, 12)], 201);
    }

    public function show(Request $request, string $inventory, string $path): BinaryFileResponse
    {
        abort_unless($request->user()?->active && ! $request->user()->must_change_password && in_array($inventory, $request->user()->inventories(), true), 404);
        abort_if(str_contains($path, '..') || str_contains($path, '\\') || str_starts_with($path, '/'), 404);
        $relative = 'attachments/'.$inventory.'/'.$path;
        abort_unless(Storage::disk('local')->exists($relative), 404);
        $absolute = Storage::disk('local')->path($relative);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolute);
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'application/pdf'], true), 404);

        return response()->file($absolute, ['Content-Type' => $mime, 'Cache-Control' => 'no-store, private', 'Content-Disposition' => ($mime === 'application/pdf' ? 'attachment' : 'inline').'; filename="'.basename($path).'"', 'X-Content-Type-Options' => 'nosniff']);
    }
}
