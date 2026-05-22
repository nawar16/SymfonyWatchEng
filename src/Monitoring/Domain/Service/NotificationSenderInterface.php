<?php

namespace App\Monitoring\Domain\Service;

interface NotificationSenderInterface
{
    public function sendIncidentAlert(int $incidentId, int $monitorId, string $message): void;
    public function sendResolutionAlert(int $incidentId, int $monitorId): void;
}
