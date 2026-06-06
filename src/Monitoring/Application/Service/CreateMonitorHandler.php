<?php

namespace App\Monitoring\Application\Service;

use App\Monitoring\Application\DTO\MonitorInput;
use App\Monitoring\Domain\Entity\Monitor;
use App\Monitoring\Domain\ValueObject\SubscriptionLimit;
use App\Monitoring\Infrastructure\Doctrine\Repository\MonitorRepository;
use App\Tenancy\Application\TenantContext;

class CreateMonitorHandler  
{
    public function __construct(
        private MonitorRepository $repository,
        private TenantContext $tenantContext
    ) 
    {}

    public function handle(MonitorInput $input): Monitor
    {
        $tenant = $this->tenantContext->getCurrentTenant();
        if ($this->repository->countByTenant($tenant) >= SubscriptionLimit::MAX_MONITORS->value) {
            throw new \Exception("Monitor limit reached for your plan.");
        }
        if ($this->repository->findOneBy(['url' => $input->url, 'tenant' => $tenant])) {
            throw new \Exception("This URL is already being monitored.");
        }
        $monitor = new Monitor(
            $input->url,
            $input->frequency,
            $tenant
        );
        $this->repository->save($monitor);
        return $monitor;
    }
}
