<?php

namespace App\Monitoring\Domain\Event;

readonly class IncidentResolvedEvent
{
    public function __construct(
        public int $incidentId,
        public int $monitorId
    ) {}
}
