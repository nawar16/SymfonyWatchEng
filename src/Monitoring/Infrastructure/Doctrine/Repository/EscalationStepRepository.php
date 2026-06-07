<?php

namespace App\Monitoring\Infrastructure\Doctrine\Repository;

use App\Monitoring\Domain\Entity\EscalationStep;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EscalationStepRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EscalationStep::class);
    }

    public function findByMonitorIdSorted(int $monitorId): array
    {
        return $this->findBy(
            ['monitorId' => $monitorId],
            ['escalateAfterMinutes' => 'ASC']
        );
    }
}
