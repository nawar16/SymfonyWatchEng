<?php

namespace App\Monitoring\Application\CommandHandler;

use App\Monitor\Domain\Service\PingServiceInterface;
use App\Monitoring\Application\Command\CheckMonitorCommand;
use App\Monitoring\Domain\Entity\HealthCheck;
use App\Monitoring\Infrastructure\Doctrine\Repository\HealthCheckRepository;
use App\Monitoring\Infrastructure\Doctrine\Repository\MonitorRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Monitoring\Domain\Service\MonitorStateStoreInterface;

#[AsMessageHandler]
class CheckMonitorHandler
{
    public function __construct(
        private MonitorRepository $monitorRepository,
        private HealthCheckRepository $healthCheckRepository,
        private HttpClientInterface $httpClient,
        private PingServiceInterface $pingService,
        private MonitorStateStoreInterface $stateStore 
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
        if (!$result['success']) {
            $currentFailures = $this->stateStore->incrementFailures($monitor->getId());
            if ($currentFailures === 3) {
                //TODO: incident engine logic here
                $this->stateStore->setActiveIncident($monitor->getId(), [
                    'incident_id' => bin2hex(random_bytes(4)), 
                    'started_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
                ]);
            }
        } else {
            $this->stateStore->resetFailures($monitor->getId());
            $this->stateStore->clearActiveIncident($monitor->getId());
        }
    }
}
