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

    public function findAllByTenant(Tenant $tenant): array
    {
        return $this->findBy(['tenant' => $tenant]);
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

    public function remove(Monitor $monitor): void
    {
        $this->getEntityManager()->remove($monitor);
        $this->getEntityManager()->flush();
    }
    /**
     * @return Monitor[]
     */
    public function findDueMonitors(int $limit = 1000): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isActive = :active')
            ->andWhere('m.nextCheckAt <= :now')
            ->setParameter('active', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }   
}
