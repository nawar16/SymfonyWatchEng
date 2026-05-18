<?php

namespace App\Monitoring\Application\DTO;

use App\Monitoring\Domain\Entity\Monitor;

readonly class MonitorResponse
{
    public int $id;
    public string $url;
    public int $frequency;

    public function __construct(
        Monitor $monitor,
        public string $status,
        public ?int $responseTime,
        public ?string $lastCheck,
        public ?int $statusCode,
        public bool $hasIncident
    ) {
        $this->id = $monitor->getId();
        $this->url = $monitor->getUrl();
        $this->frequency = $monitor->getFrequency();
    }
}
