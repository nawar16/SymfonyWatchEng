<?php

namespace App\Monitoring\Application\EventHandler;

use App\Monitoring\Domain\Event\IncidentCreatedEvent;
use App\Monitoring\Domain\Event\IncidentResolvedEvent;
use App\Monitoring\Domain\Service\NotificationSenderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

class NotificationHandler
{
    public function __construct(
        private NotificationSenderInterface $notificationSender
    ) {}

    #[AsMessageHandler]
    public function onIncidentCreated(IncidentCreatedEvent $event): void
    {
        // notifications 3rd parties
        // $this->mailer->send(...);
        // $this->slackClient->post(...);
    }

    #[AsMessageHandler]
    public function onIncidentResolved(IncidentResolvedEvent $event): void
    {
        // recovery notification 
    }
}
