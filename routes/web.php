<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChangeRequestController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\ActiveUser;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app')->name('home');
Route::get('/login', fn () => redirect()->route('home'))->name('login');
Route::prefix('api/v1')->group(function () {
    Route::get('session', [AuthController::class, 'show']);
    Route::post('login', [AuthController::class, 'store'])->middleware('throttle:login');
    Route::post('logout', [AuthController::class, 'destroy']);
    Route::middleware(['auth', ActiveUser::class, 'throttle:120,1'])->group(function () {
        Route::post('password', [AuthController::class, 'password']);
        Route::get('dashboard', [WorkspaceController::class, 'dashboard']);
        Route::get('history', [WorkspaceController::class, 'history']);
        Route::get('archive', [WorkspaceController::class, 'archive']);
        Route::get('reference', [WorkspaceController::class, 'reference']);
        Route::post('checks', [WorkspaceController::class, 'check']);
        Route::get('export', [WorkspaceController::class, 'export']);
        Route::post('scan', [InventoryController::class, 'scan']);
        Route::apiResource('items', InventoryController::class)->parameters(['items' => 'item']);
        Route::get('loans', [LoanController::class, 'index']);
        Route::post('loans', [LoanController::class, 'store']);
        Route::post('loans/{loan}/{action}', [LoanController::class, 'update'])->whereIn('action', ['approve', 'reject', 'request-return', 'return', 'reject-return']);
        Route::get('changes', [ChangeRequestController::class, 'index']);
        Route::put('changes/{change}', [ChangeRequestController::class, 'update']);
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::post('attachments', [AttachmentController::class, 'store'])->middleware('throttle:20,1');
    });
});
Route::get('/files/{inventory}/{path}', [AttachmentController::class, 'show'])->where('path','.*')->middleware('auth')->name('attachments.show');
