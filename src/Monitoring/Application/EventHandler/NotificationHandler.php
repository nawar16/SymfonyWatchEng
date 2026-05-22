<?php

namespace App\Monitoring\Application\EventHandler;

use App\Monitoring\Domain\Event\IncidentCreatedEvent;
use App\Monitoring\Domain\Event\IncidentResolvedEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

class NotificationHandler
{
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
