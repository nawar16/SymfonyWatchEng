<?php

namespace App\Monitoring\Application\Command;

readonly class CheckEscalationCommand
{
    public function __construct(
        public int $incidentId,
        public int $currentStepIndex = 0
    ) {}
}
