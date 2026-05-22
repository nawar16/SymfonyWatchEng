<?php

namespace App\Monitoring\Domain\Event;

readonly class IncidentCreatedEvent
{
    public function __construct(
        public int $incidentId,
        public int $monitorId,
        public string $errorMessage
    ) {}
}
