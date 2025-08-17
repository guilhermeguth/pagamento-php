<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalAuthorizationService
{
    private string $authUrl;

    public function __construct()
    {
        $this->authUrl = config('services.external_auth.url', 'https://util.devi.tools/api/v2/authorize');
    }

    public function authorize(): bool
    {
        try {
            $response = Http::timeout(10)->get($this->authUrl);

            if (!$response->successful()) {
                Log::warning('External authorization service returned non-successful response', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }

            $data = $response->json();

            // O mock retorna {"message": "Autorizado"} quando autorizado
            return isset($data['message']) && $data['message'] === 'Autorizado';

        } catch (\Exception $e) {
            Log::error('External authorization service failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
