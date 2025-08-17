<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('system:info', function () {
    $this->info('Sistema de Pagamento PHP');
    $this->info('API: http://localhost:8000/api');
    $this->info('Frontend: http://localhost:5173');
})->purpose('Mostra informações do sistema');
