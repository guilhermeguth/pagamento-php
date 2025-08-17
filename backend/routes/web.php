<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Sistema de Pagamento API',
        'api' => url('/api'),
        'frontend' => 'http://localhost:5173'
    ]);
});
