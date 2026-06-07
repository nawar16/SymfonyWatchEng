<?php

namespace App\Monitoring\Infrastructure\Doctrine\Repository;

use App\Monitoring\Domain\Entity\Monitor;
use App\Monitoring\Domain\Entity\NotificationRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NotificationRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationRule::class);
    }

    public function findByMonitorId(int $monitorId): ?NotificationRule
    {
        return $this->findOneBy(['monitorId' => $monitorId]);
    }

}
