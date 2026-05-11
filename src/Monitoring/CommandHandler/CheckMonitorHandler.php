<?php

namespace App\Monitoring\Application\CommandHandler;

use App\Monitor\Domain\Service\PingServiceInterface;
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
        private HttpClientInterface $httpClient,
        private PingServiceInterface $pingService 
    ) {}

    public function __invoke(CheckMonitorCommand $command): void
    {
        $monitor = $this->monitorRepository->find($command->monitorId);
        if (!$monitor) return;
        $result = $this->pingService->ping($monitor->getUrl());
        $check = new HealthCheck(
            $monitor->getId(),
            $result['status_code'],
            $result['response_time'],
            $result['success']
        );
        // TODO: update Redis for realtime dashboard
        // $this->redisStateStore->updateStatus($monitor->getId(), $isSuccess);
        $this->healthCheckRepository->save($check, true);
    }
}
