<?php

namespace App\Monitoring\Application\Service;

use App\Monitoring\Application\DTO\MonitorInput;
use App\Monitoring\Domain\Entity\Monitor;
use App\Monitoring\Infrastructure\Doctrine\Repository\MonitorRepository;
use App\Tenancy\Application\TenantContext;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MonitorService
{
    private const MAX_MONITORS = 50;

    public function __construct(
        private MonitorRepository $repository,
        private TenantContext $tenantContext
    ) 
    {}

    public function create(MonitorInput $input): Monitor
    {
        $tenant = $this->tenantContext->getCurrentTenant();
        if ($this->repository->countByTenant($tenant) >= self::MAX_MONITORS) {
            throw new BadRequestHttpException("Limit of " . self::MAX_MONITORS . " monitors reached.");
        }
        if ($this->repository->findOneBy(['url' => $input->url, 'tenant' => $tenant])) {
            throw new BadRequestHttpException("This URL is already being monitored.");
        }
        $monitor = new Monitor($input->url, $input->frequency, $tenant);
        $this->repository->save($monitor);
        return $monitor;
    }

    public function listAll(): array
    {
        $tenant = $this->tenantContext->getCurrentTenant();
        return $this->repository->findAllByTenant($tenant);
    }

    public function update(int $id, MonitorInput $input): Monitor
    {
        $monitor = $this->repository->find($id);
        
        if (!$monitor) {
            throw new NotFoundHttpException();
        }
        if ($monitor->getTenant() !== $this->tenantContext->getCurrentTenant()) {
            throw new AccessDeniedHttpException("Unauthorized access to this monitor.");
        }
        $monitor->setUrl($input->url);
        $monitor->setFrequency($input->frequency);
        $this->repository->save($monitor);
        return $monitor;
    }

    public function delete(int $id): void
    {
        $monitor = $this->repository->find($id);
        if ($monitor) {
            if ($monitor->getTenant() !== $this->tenantContext->getCurrentTenant()) {
                throw new AccessDeniedHttpException();
            }
            $this->repository->remove($monitor);
        }
    }
}
