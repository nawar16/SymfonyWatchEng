<?php

namespace App\Monitoring\Application\CommandHandler;

use App\Monitoring\Application\Command\CheckMonitorCommand;
use App\Monitoring\Domain\Entity\HealthCheck;
use App\Monitoring\Infrastructure\Doctrine\Repository\HealthCheckRepository;
use App\Monitoring\Infrastructure\Doctrine\Repository\MonitorRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class CheckMonitorHandler
{
    public function __construct(
        private MonitorRepository $monitorRepository,
        private HealthCheckRepository $healthCheckRepository,
        private HttpClientInterface $httpClient
    ) {}

    public function __invoke(CheckMonitorCommand $command): void
    {
        $monitor = $this->monitorRepository->find($command->monitorId);
        if (!$monitor) {
            return; 
        }
        $start = microtime(true);
        try {
            $response = $this->httpClient->request('GET', $monitor->getUrl(), [
                'timeout' => 10, 
                'max_redirects' => 3,
            ]);
            $statusCode = $response->getStatusCode();
            $isSuccess = ($statusCode >= 200 && $statusCode < 300); 
        } catch (\Exception $e) {
            $statusCode = 0; 
            $isSuccess = false;
        }
        $duration = (int) ((microtime(true) - $start) * 1000); 
        $check = new HealthCheck(
            $monitor->getId(),
            $statusCode,
            $duration,
            $isSuccess
        );
        $this->healthCheckRepository->save($check, true);

        // TODO: update Redis for realtime dashboard
        // $this->redisStateStore->updateStatus($monitor->getId(), $isSuccess);
    }
}
