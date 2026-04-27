<?php

namespace App\Repository;

use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tenant>
 */
class TenantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tenant::class);
    }

    public function findOneByHostname(string $hostname): ?Tenant
    {
        $normalizedHost = mb_strtolower($hostname);
        $candidates = [$normalizedHost];

        if (str_contains($normalizedHost, '.')) {
            $candidates[] = explode('.', $normalizedHost)[0];
        }

        $candidates = array_values(array_unique($candidates));

        return $this->createQueryBuilder('tenant')
            ->andWhere('tenant.subdomain IN (:candidates)')
            ->setParameter('candidates', $candidates)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
