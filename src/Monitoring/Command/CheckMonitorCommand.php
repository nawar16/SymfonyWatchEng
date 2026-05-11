<?php

namespace App\Monitoring\Application\Command;

readonly class CheckMonitorCommand
{
    public function __construct(
        public int $monitorId
    ) {}
}
