<?php

namespace App\EventListener;

use App\Entity\Tenant;
use App\Repository\TenantRepository;
use App\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest')]
class TenantListener
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly TenantContext $tenantContext,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $host = mb_strtolower($request->getHost());

        if ($this->shouldSkipTenantResolution($host)) {
            $this->tenantContext->setTenant(null);

            return;
        }

        $tenant = $this->tenantRepository->findOneByHostname($host);

        if ($tenant === null) {
            throw new NotFoundHttpException(sprintf('No tenant matched the host "%s".', $host));
        }

        $this->tenantContext->setTenant($tenant);

        $filters = $this->entityManager->getFilters();
        $filter = $filters->isEnabled('tenant')
            ? $filters->getFilter('tenant')
            : $filters->enable('tenant');

        $filter->setParameter('tenant_id', (string) $tenant->getId());
        $request->attributes->set(Tenant::class, $tenant);
    }

    private function shouldSkipTenantResolution(string $host): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false;
    }
}
