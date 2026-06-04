<?php

namespace App\Monitoring\Application\EventHandler;

use App\Monitoring\Application\Command\CheckEscalationCommand;
use App\Monitoring\Domain\Entity\EscalationStep;
use App\Monitoring\Domain\Entity\NotificationRule;
use App\Monitoring\Domain\Event\IncidentCreatedEvent;
use App\Monitoring\Domain\Event\IncidentResolvedEvent;
use App\Monitoring\Domain\Service\NotificationSenderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class NotificationHandler
{
    public function __construct(
        private NotificationSenderInterface $notificationSender,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $commandBus
    ) {}

    #[AsMessageHandler] 
    public function onIncidentCreated(IncidentCreatedEvent $event): void
    {
        $monitorId = $event->monitorId;
        //TODO: implement the cooldown protection here
        $rule = $this->entityManager->getRepository(NotificationRule::class)
            ->findOneBy(['monitorId' => $monitorId]);
            
        if ($rule && $rule->isOnlyBusinessHours() && !$this->isBusinessHours()) {
            return; 
        }
        $delayMinutes = $rule ? $rule->getDelayMinutes() : 0;
        $stamps = [];
        if ($delayMinutes > 0) {
            $stamps[] = new DelayStamp($delayMinutes * 60 * 1000);
        }

        //handler via step index 0
        $this->commandBus->dispatch(
            new CheckEscalationCommand($event->incidentId, 0),
            $stamps
        );
    }

    #[AsMessageHandler]
    public function onIncidentResolved(IncidentResolvedEvent $event): void
    {
        $this->notificationSender->sendResolutionAlert($event->incidentId, $event->monitorId);
    }
    
    private function isBusinessHours(): bool
    {
        $currentHour = (int)(new \DateTimeImmutable())->format('H');
        $currentDay = (int)(new \DateTimeImmutable())->format('N'); // 1 mon to 7 sun        
        return ($currentDay <= 5 && $currentHour >= 9 && $currentHour <= 17);
    }
}
