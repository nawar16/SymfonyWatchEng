<?php

namespace App\Monitoring\Infrastructure\External;

use App\Monitoring\Domain\Service\MonitorStateStoreInterface;
use Predis\Client as Redis;

class RedisMonitorStateStore implements MonitorStateStoreInterface
{
    public function __construct(private Redis $redis) 
    {}

    public function updateStatus(int $monitorId, string $status, int $responseTime, int $statusCode): void
    {
        $key = sprintf('monitor:%d:status', $monitorId);
        $data = json_encode([
            'status' => $status,
            'response_time' => $responseTime,
            'checked_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'status_code' => $statusCode
        ]);
        $this->redis->set($key, $data);
    }

    public function incrementFailures(int $monitorId): int
    {
        $key = sprintf('monitor:%d:failures', $monitorId);
        return $this->redis->incr($key);
    }
    public function resetFailures(int $monitorId): void
    {
        $key = sprintf('monitor:%d:failures', $monitorId);
        $this->redis->set($key, 0);
    }

    public function setActiveIncident(int $monitorId, array $incidentData): void
    {
        $key = sprintf('monitor:%d:incident', $monitorId);
        $this->redis->set($key, json_encode($incidentData));
    }
    public function clearActiveIncident(int $monitorId): void
    {
        $key = sprintf('monitor:%d:incident', $monitorId);
        $this->redis->del($key);
    }
    public function getStatusSnapshot(int $monitorId): ?array
    {
        $key = sprintf('monitor:%d:status', $monitorId);
        $rawJson = $this->redis->get($key);
        if (!$rawJson) {
            return null;
        }
        return json_decode($rawJson, true) ?: null;
    }

    public function hasActiveIncident(int $monitorId): bool
    {
        $key = sprintf('monitor:%d:incident', $monitorId);
        return (bool) $this->redis->exists($key);
    }
    public function getActiveIncidentId(int $monitorId): ?int
    {
        $rawJson = $this->redis->get(sprintf('monitor:%d:incident', $monitorId));
        if (!$rawJson) {
            return null;
        }
        $data = json_decode($rawJson, true);
        return $data['incident_id'] ?? null;
    }

}
