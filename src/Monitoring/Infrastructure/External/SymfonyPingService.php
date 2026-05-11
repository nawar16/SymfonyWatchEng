<?php

namespace App\Monitor\Infrastructure\External;

use App\Monitor\Domain\Service\PingServiceInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyPingService implements PingServiceInterface
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    public function ping(string $url): array
    {
        $start = microtime(true);
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 7,      
                'max_redirects' => 2,   
                'user_agent' => 'HeartbeatEngine/1.0',
            ]);
            $statusCode = $response->getStatusCode();
            $isSuccess = ($statusCode >= 200 && $statusCode < 300);

        } catch (\Throwable) {
            $statusCode = 0; // unreachable
            $isSuccess = false;
        }
        $duration = (int) ((microtime(true) - $start) * 1000);
        return [
            'status_code' => $statusCode,
            'response_time' => $duration,
            'success' => $isSuccess,
        ];
    }
}
