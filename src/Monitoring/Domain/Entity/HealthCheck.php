<?php

namespace App\Monitoring\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "health_checks")]
#[ORM\Index(columns: ["monitor_id", "checked_at"])]
class HealthCheck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $monitorId;

    #[ORM\Column(type: "smallint")]
    private int $statusCode;

    #[ORM\Column]
    private int $responseTimeMs;

    #[ORM\Column]
    private \DateTimeImmutable $checkedAt;

    #[ORM\Column]
    private bool $isSuccess;

    public function __construct(
        int $monitorId,
        int $statusCode,
        int $responseTimeMs,
        bool $isSuccess
    ) {
        $this->monitorId = $monitorId;
        $this->statusCode = $statusCode;
        $this->responseTimeMs = $responseTimeMs;
        $this->isSuccess = $isSuccess;
        $this->checkedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getMonitorId(): int { return $this->monitorId; }
    public function getStatusCode(): int { return $this->statusCode; }
    public function getResponseTimeMs(): int { return $this->responseTimeMs; }
    public function getCheckedAt(): \DateTimeImmutable { return $this->checkedAt; }
    public function isSuccess(): bool { return $this->isSuccess; }
}
