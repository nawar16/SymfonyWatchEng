<?php

namespace App\Monitoring\Infrastructure\Doctrine\Repository;

use App\Monitoring\Domain\Entity\Monitor;
use App\Tenancy\Domain\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MonitorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Monitor::class);
    }

    public function countByTenant(Tenant $tenant): int
    {
        return $this->count(['tenant' => $tenant]);
    }

    public function save(Monitor $monitor): void
    {
        $this->getEntityManager()->persist($monitor);
        $this->getEntityManager()->flush();
    }
}
