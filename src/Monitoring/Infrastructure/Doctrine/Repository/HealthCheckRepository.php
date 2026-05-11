<?php

namespace App\Monitor\Infrastructure\Doctrine\Repository;

use App\Monitor\Domain\Entity\HealthCheck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HealthCheck>
 */
class HealthCheckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HealthCheck::class);
    }

    public function save(HealthCheck $healthCheck, bool $flush = false): void
    {
        $this->getEntityManager()->persist($healthCheck);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function deleteOlderThan(\DateTimeImmutable $date): int
    {
        return $this->createQueryBuilder('h')
            ->delete()
            ->where('h.checkedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
