<?php

namespace App\Tenancy\Infrastructure\Doctrine\Repository;

use App\Tenancy\Domain\Entity\Tenant;
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

    public function findOneBySubdomain(string $subdomain): ?Tenant
    {
        return $this->findOneBy([
            'subdomain' => mb_strtolower($subdomain),
        ]);
    }
}
