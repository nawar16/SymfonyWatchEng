<?php

namespace App\Monitoring\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "incidents")]
#[ORM\Index(columns: ["monitor_id", "status"])]
class Incident
{
    public const STATUS_DOWN = 'DOWN';
    public const STATUS_INVESTIGATING = 'INVESTIGATING';
    public const STATUS_RESOLVED = 'RESOLVED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $monitorId;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(length: 20)]
    private string $status;

    #[ORM\Column]
    private int $failureCount;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $lastError = null;

    public function __construct(int $monitorId, int $failureCount, ?string $lastError = null)
    {
        $this->monitorId = $monitorId;
        $this->failureCount = $failureCount;
        $this->lastError = $lastError;
        $this->status = self::STATUS_DOWN;
        $this->startedAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getMonitorId(): int { return $this->monitorId; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getResolvedAt(): ?\DateTimeImmutable { return $this->resolvedAt; }
    public function getStatus(): string { return $this->status; }
    public function getFailureCount(): int { return $this->failureCount; }
    public function getLastError(): ?string { return $this->lastError; }

 
    public function updateFailureCount(int $count, ?string $error): void
    {
        $this->failureCount = $count;
        $this->lastError = $error;
    }

    public function transitionToInvestigating(): void
    {
        $this->status = self::STATUS_INVESTIGATING;
    }
    public function resolve(): void
    {
       $this->status = self::STATUS_RESOLVED;
        $this->resolvedAt = new \DateTimeImmutable();
    }
}
