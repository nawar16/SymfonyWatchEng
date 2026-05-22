<?php

namespace App\Monitoring\Application\CommandHandler;

use App\Monitoring\Domain\Service\PingServiceInterface;
use App\Monitoring\Application\Command\CheckMonitorCommand;
use App\Monitoring\Domain\Entity\HealthCheck;
use App\Monitoring\Domain\Service\IncidentEngineInterface;
use App\Monitoring\Infrastructure\Doctrine\Repository\HealthCheckRepository;
use App\Monitoring\Infrastructure\Doctrine\Repository\MonitorRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Monitoring\Domain\Service\MonitorStateStoreInterface;
use App\Monitoring\Infrastructure\Service\IncidentEngine;

#[AsMessageHandler]
class CheckMonitorHandler
{
    public function __construct(
        private MonitorRepository $monitorRepository,
        private HealthCheckRepository $healthCheckRepository,
        private PingServiceInterface $pingService,
        private MonitorStateStoreInterface $stateStore ,
        private IncidentEngine $incidentEngine 
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
        $this->healthCheckRepository->save($check, true);

        // 
        $status = $result['success'] ? 'UP' : 'DOWN';
        $this->stateStore->updateStatus(
            $monitor->getId(),
            $status,
            $result['response_time'],
            $result['status_code']
        );
        $errorMsg = $result['success'] ? '' : "HTTP status check returned code: " . $result['status_code'];
        $this->incidentEngine->handleSignal(
            $monitor->getId(),
            $result['success'],
            $result['status_code'],
            $errorMsg
        );
    }
}
