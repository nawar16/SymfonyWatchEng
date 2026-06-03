<?php

namespace App\Monitoring\Application\CommandHandler;

use App\Monitoring\Application\Command\CheckEscalationCommand;
use App\Monitoring\Domain\Entity\Incident;
use App\Monitoring\Domain\Entity\EscalationStep;
use App\Monitoring\Domain\Entity\NotificationRule;
use App\Monitoring\Domain\Service\NotificationSenderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
class CheckEscalationHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationSenderInterface $notificationSender,
        private MessageBusInterface $commandBus
    ) {}

    public function __invoke(CheckEscalationCommand $command): void
    {
        $incident = $this->entityManager->getRepository(Incident::class)->find($command->incidentId);
        //resolved or missing
        if (!$incident || $incident->getStatus() === Incident::STATUS_RESOLVED) {
            return;
        }

        $currentStepIndex = $command->currentStepIndex;
        $monitorId = $incident->getMonitorId();
        //step 0
        if ($currentStepIndex === 0) {
            //the initial alert
            $this->notificationSender->sendIncidentAlert(
                $incident->getId(), 
                $monitorId, 
                "Monitor down: " . $incident->getErrorMessage()
            );
            $firstStep = $this->entityManager->getRepository(EscalationStep::class)
                ->findOneBy(['monitorId' => $monitorId], ['escalateAfterMinutes' => 'ASC']);
            if ($firstStep) {
                $this->commandBus->dispatch(
                    new CheckEscalationCommand($incident->getId(), 1),
                    [new DelayStamp($firstStep->getEscalateAfterMinutes() * 60 * 1000)]
                );
            }
            return;
        }

        //other steps 1, 2, 3...
        $steps = $this->entityManager->getRepository(EscalationStep::class)
            ->findBy(['monitorId' => $monitorId], ['escalateAfterMinutes' => 'ASC']);
        $arrayIndex = $currentStepIndex - 1;

        if (isset($steps[$arrayIndex])) {
            /** @var EscalationStep $currentStep */
            $currentStep = $steps[$arrayIndex];

            $this->notificationSender->sendEscalationAlert(
                $incident->getId(), 
                $monitorId, 
                $currentStep->getChannel(), 
                sprintf("ESCALATION LEVEL %d: Monitor remains DOWN!", $currentStepIndex)
            );

            //next tier
            $nextStepIndex = $currentStepIndex + 1;
            $nextArrayIndex = $currentStepIndex;
            if (isset($steps[$nextArrayIndex])) {
                $delaySeconds = ($steps[$nextArrayIndex]->getEscalateAfterMinutes() - $currentStep->getEscalateAfterMinutes()) * 60;
                $this->commandBus->dispatch(
                    new CheckEscalationCommand($incident->getId(), $nextStepIndex),
                    [new DelayStamp($delaySeconds * 1000)]
                );
            }
        }
    }
}
