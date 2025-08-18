<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/find-by-email', [UserController::class, 'findByEmail']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::get('/{id}/balance', [UserController::class, 'balance']);
    });

    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::get('/{id}', [TransactionController::class, 'show']);
    });

    Route::prefix('transfers')->group(function () {
        Route::post('/', [TransferController::class, 'transfer']);
        Route::post('/deposit', [TransferController::class, 'deposit']);
        Route::post('/withdraw', [TransferController::class, 'withdraw']);
        Route::post('/{id}/refund', [TransferController::class, 'refund']);
    });
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toISOString(),
        'service' => 'Sistema de Pagamento API'
    ]);
});
