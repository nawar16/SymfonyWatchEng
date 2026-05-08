<?php

namespace App\Monitoring\Application\Service;

use App\Monitoring\Application\DTO\MonitorInput;
use App\Monitoring\Domain\Entity\Monitor;
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
        if ($this->repository->countByTenant($tenant) >= 5) {
            throw new \Exception("Monitor limit reached for your plan.");
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
