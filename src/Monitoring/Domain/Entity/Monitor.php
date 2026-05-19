<?php

namespace App\Monitoring\Domain\Entity;

use App\Tenancy\Domain\Entity\Tenant;
use Doctrine\ORM\Mapping as ORM;
use App\Monitoring\Infrastructure\Doctrine\Repository\MonitorRepository;
use App\Shared\Domain\TenantScopedInterface;
use Symfony\Component\Serializer\Annotation\Groups; 

#[ORM\Entity(repositoryClass: MonitorRepository::class)]
#[ORM\Table(name: "monitors")]
#[ORM\Index(columns: ["next_check_at", "is_active"])] 
class Monitor implements TenantScopedInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[Groups(['monitor:read'])] 
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['monitor:read'])] 
    private string $url;

    #[ORM\Column(type: "boolean")]
    private ?bool $isActive = true;

    #[ORM\Column]
    #[Groups(['monitor:read'])] 
    private int $frequency = 60;

    #[ORM\Column]
    private int $expectedStatusCode = 200;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Tenant $tenant;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $nextCheckAt = null;

    public function __construct(string $url, int $frequency, Tenant $tenant)
    {
        $this->url = $url;
        $this->frequency = $frequency;
        $this->tenant = $tenant;
        $this->createdAt = new \DateTimeImmutable();
        $this->isActive = true;
        $randomDelay = rand(0, $frequency);
        $this->nextCheckAt = (new \DateTimeImmutable())->modify("+{$randomDelay} seconds");

    }

    public function getId(): ?int { return $this->id; }
    public function getUrl(): string { return $this->url; }
    public function setUrl(string $url): void { $this->url = $url; }
    public function getFrequency(): int { return $this->frequency; }
    public function setFrequency(int $frequency): void { $this->frequency = $frequency; }
    public function getExpectedStatusCode(): int { return $this->expectedStatusCode; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getNextCheckAt(): ?\DateTimeImmutable { return $this->nextCheckAt; }
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }
    public function setTenant(?Tenant $tenant): static
    {
        $this->tenant = $tenant;
        return $this;
    }
    public function calculateNextCheck(): void
    {
        $this->nextCheckAt = (new \DateTimeImmutable())->modify("+{$this->frequency} seconds");
    }
}
