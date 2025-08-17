<?php

namespace App\Services\ExternalServices;

use App\Entities\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthorizationService
{
    protected string $authorizationUrl;
    protected int $timeout;

    public function __construct()
    {
        // URL do mock do serviço autorizador
        $this->authorizationUrl = 'https://util.devi.tools/api/v2/authorize';
        $this->timeout = 5; // 5 segundos de timeout
    }

    /**
     * Consultar serviço externo para autorização de transferência
     */
    public function authorize(User $sender, User $recipient, float $amount): bool
    {
        try {
            Log::info('Consultando serviço autorizador externo', [
                'sender_id' => $sender->getId()->toString(),
                'recipient_id' => $recipient->getId()->toString(),
                'amount' => $amount
            ]);

            $response = Http::timeout($this->timeout)->get($this->authorizationUrl);

            if (!$response->successful()) {
                Log::warning('Serviço autorizador indisponível', [
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
                
                // Em caso de indisponibilidade do serviço, podemos definir uma política
                // Por segurança, vamos negar por padrão
                return false;
            }

            $data = $response->json();
            
            $data = $response->json();
            
            // O mock retorna {"status": "success", "data": {"authorization": true}}
            // Verificamos tanto o status quanto a autorização
            $authorized = (isset($data['status']) && $data['status'] === 'success') ||
                         (isset($data['data']['authorization']) && $data['data']['authorization'] === true);

            Log::info('Resposta do serviço autorizador', [
                'authorized' => $authorized,
                'response' => $data
            ]);

            return $authorized;

        } catch (Exception $e) {
            Log::error('Erro ao consultar serviço autorizador', [
                'error' => $e->getMessage(),
                'sender_id' => $sender->getId()->toString(),
                'recipient_id' => $recipient->getId()->toString(),
                'amount' => $amount
            ]);

            // Em caso de erro na consulta, negamos por segurança
            return false;
        }
    }

    /**
     * Verificar se o serviço está disponível
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(3)->get($this->authorizationUrl);
            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }
}
