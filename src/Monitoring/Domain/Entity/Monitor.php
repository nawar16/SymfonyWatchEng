<?php

namespace App\Monitoring\Domain\Entity;

use App\Tenancy\Domain\Entity\Tenant;
use Doctrine\ORM\Mapping as ORM;
use App\Monitoring\Infrastructure\Doctrine\Repository\MonitorRepository;
use App\Shared\Domain\TenantScopedInterface;

#[ORM\Entity(repositoryClass: MonitorRepository::class)]
class Monitor implements TenantScopedInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $url;

    #[ORM\Column]
    private int $frequency = 60;

    #[ORM\Column]
    private int $expectedStatusCode = 200;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Tenant $tenant;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $url, int $frequency, Tenant $tenant)
    {
        $this->url = $url;
        $this->frequency = $frequency;
        $this->tenant = $tenant;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUrl(): string { return $this->url; }
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }
    public function setTenant(?Tenant $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

}
