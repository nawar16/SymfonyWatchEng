<?php

namespace App\Monitoring\Domain\Service;

interface MonitorStateStoreInterface
{
    public function updateStatus(int $monitorId, string $status, int $responseTime, int $statusCode): void;
    public function incrementFailures(int $monitorId): int;
    public function resetFailures(int $monitorId): void;
    public function setActiveIncident(int $monitorId, array $incidentData): void;
    public function clearActiveIncident(int $monitorId): void;
    public function getStatusSnapshot(int $id): ?array;
    public function hasActiveIncident(int $id): bool;
    public function getActiveIncidentId(int $monitorId): ?int;
    public function tryAcquireNotificationCooldown(int $monitorId, int $cooldownSeconds): bool;
}
