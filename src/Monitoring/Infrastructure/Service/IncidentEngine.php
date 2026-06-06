<?php

namespace App\Monitoring\Infrastructure\Service;

use App\Monitoring\Domain\Entity\Incident;
use App\Monitoring\Domain\Event\IncidentCreatedEvent;
use App\Monitoring\Domain\Event\IncidentResolvedEvent;
use App\Monitoring\Domain\Service\IncidentEngineInterface;
use App\Monitoring\Domain\Service\MonitorStateStoreInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class IncidentEngine implements IncidentEngineInterface
{
    private const CONSECUTIVE_FAILURE_THRESHOLD = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MonitorStateStoreInterface $stateStore,
        private MessageBusInterface $eventBus
    ) {}

    public function handleSignal(int $monitorId, bool $isSuccess, int $statusCode, string $errorMessage = ''): void
    {
        $activeIncidentData = $this->stateStore->getStatusSnapshot($monitorId);
        $hasActiveIncident = $this->stateStore->hasActiveIncident($monitorId);

        if (!$isSuccess) {
            $consecutiveFailures = $this->stateStore->incrementFailures($monitorId);
            if ($consecutiveFailures >= self::CONSECUTIVE_FAILURE_THRESHOLD) {
                if (!$hasActiveIncident) {
                    //persistent incident
                    $incident = new Incident($monitorId, $consecutiveFailures, $errorMessage);
                    $this->entityManager->persist($incident);
                    $this->entityManager->flush();

                    //for dash
                    $this->stateStore->setActiveIncident($monitorId, [
                        'incident_id' => $incident->getId(),
                        'started_at' => $incident->getStartedAt()->format(\DateTimeInterface::ATOM)
                    ]);
                    $this->eventBus->dispatch(new IncidentCreatedEvent(
                        $incident->getId(),
                        $monitorId,
                        $errorMessage
                    ));
                } else {
                    $incidentId = $this->stateStore->getActiveIncidentId($monitorId);
                    if ($incidentId) {
                        $incident = $this->entityManager->getRepository(Incident::class)->find($incidentId);
                        $incident?->updateFailureCount($consecutiveFailures, $errorMessage);
                        $this->entityManager->flush();
                    }
                }
            }
        } else {
            $this->stateStore->resetFailures($monitorId);
            if ($hasActiveIncident) {
                $incidentId = $this->stateStore->getActiveIncidentId($monitorId);
                if ($incidentId) {
                    $incident = $this->entityManager->getRepository(Incident::class)->find($incidentId);
                    if ($incident && $incident->getStatus() !== Incident::STATUS_RESOLVED) {
                        $incident->resolve();
                        $this->entityManager->flush();
                        $this->stateStore->clearActiveIncident($monitorId);
                        $this->eventBus->dispatch(new IncidentResolvedEvent($incidentId, $monitorId));
                    }
                }
            }
        }
    }
}
