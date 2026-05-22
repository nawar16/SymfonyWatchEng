<?php

namespace App\Monitoring\Domain\Service;

interface IncidentEngineInterface
{
    public function handleSignal(
        int $monitorId, 
        bool $isSuccess, 
        int $statusCode, 
        string $errorMessage = ''
    ): void;
}
