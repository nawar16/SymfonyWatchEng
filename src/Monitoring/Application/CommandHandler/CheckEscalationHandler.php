<?php

namespace App\Monitoring\Application\CommandHandler;

use App\Monitoring\Application\Command\CheckEscalationCommand;
use App\Monitoring\Domain\Entity\Incident;
use App\Monitoring\Domain\Entity\EscalationStep;
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

        //all escalation levels for this monitor
        $steps = $this->entityManager->getRepository(EscalationStep::class)
            ->findBy(['monitorId' => $incident->getMonitorId()], ['escalateAfterMinutes' => 'ASC']);
        $currentStepIndex = $command->currentStepIndex;

        if (isset($steps[$currentStepIndex])) {
            /** @var EscalationStep $currentStep */
            $currentStep = $steps[$currentStepIndex];

            $this->notificationSender->sendEscalationAlert($incident->getId(), $incident->getMonitorId(), $currentStep->getChannel(), 
            sprintf("ESCALATION LEVEL %d: Monitor remains DOWN!", $currentStepIndex + 1));

            //next escalation milestone
            $nextStepIndex = $currentStepIndex + 1;
            if (isset($steps[$nextStepIndex])) {
                $delaySeconds = ($steps[$nextStepIndex]->getEscalateAfterMinutes() - $currentStep->getEscalateAfterMinutes()) * 60;
                $this->commandBus->dispatch(
                    new CheckEscalationCommand($incident->getId(), $nextStepIndex),
                    [new DelayStamp($delaySeconds * 1000)]
                );
            }
        }
    }
}
