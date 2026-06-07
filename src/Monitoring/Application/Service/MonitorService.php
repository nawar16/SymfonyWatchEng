<?php

namespace App\Monitoring\Application\Service;

use App\Monitoring\Application\DTO\MonitorInput;
use App\Monitoring\Application\DTO\MonitorResponse;
use App\Monitoring\Domain\Entity\Monitor;
use App\Monitoring\Infrastructure\Doctrine\Repository\MonitorRepository;
use App\Tenancy\Application\TenantContext;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Monitoring\Domain\Service\MonitorStateStoreInterface;
use App\Monitoring\Domain\ValueObject\SubscriptionLimit;

class MonitorService 
{
    public function __construct(
        private MonitorRepository $repository,
        private TenantContext $tenantContext,
        private MonitorStateStoreInterface $stateStore
    ) 
    {}

    public function create(MonitorInput $input): Monitor
    {
        $tenant = $this->tenantContext->getCurrentTenant();
        if ($this->repository->countByTenant($tenant) >= SubscriptionLimit::MAX_MONITORS->value) {
            throw new BadRequestHttpException("Limit of " . SubscriptionLimit::MAX_MONITORS->value . " monitors reached.");
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

        $monitors = $this->repository->findAllByTenant($tenant);
        $results = [];
        foreach ($monitors as $monitor) {
            $statusData = $this->stateStore->getStatusSnapshot($monitor->getId());
            $hasIncident = $this->stateStore->hasActiveIncident($monitor->getId());
            $results[] = new MonitorResponse(
                $monitor,
                $statusData['status'] ?? 'PENDING',
                $statusData['response_time'] ?? null,
                $statusData['checked_at'] ?? null,
                $statusData['status_code'] ?? null,
                $hasIncident
            );
        }
        return $results;
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
