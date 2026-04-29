<?php

namespace App\EventListener;

use App\Entity\Tenant;
use App\Repository\TenantRepository;
use App\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
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
        $subdomain = $this->extractSubdomain($host);

        if ($subdomain === null) {
            $this->disableTenantFilter();
            $this->tenantContext->setCurrentTenant(null);

            return;
        }

        $tenant = $this->tenantRepository->findOneBySubdomain($subdomain);

        if ($tenant === null) {
            $this->disableTenantFilter();
            $this->tenantContext->setCurrentTenant(null);
            $event->setResponse(new JsonResponse([
                'error' => 'Tenant not found.',
                'subdomain' => $subdomain,
            ], Response::HTTP_NOT_FOUND));

            return;
        }

        $this->tenantContext->setCurrentTenant($tenant);

        $filters = $this->entityManager->getFilters();
        $filter = $filters->isEnabled('tenant')
            ? $filters->getFilter('tenant')
            : $filters->enable('tenant');

        $filter->setParameter('tenant_id', (string) $tenant->getId());
        $request->attributes->set(Tenant::class, $tenant);
        $request->attributes->set('tenant_subdomain', $subdomain);
    }

    private function extractSubdomain(string $host): ?string
    {
        $host = trim($host, '[]');

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        if (!str_contains($host, '.')) {
            return null;
        }

        $parts = explode('.', $host);
        $subdomain = $parts[0] ?? null;

        if ($subdomain === null || $subdomain === '' || in_array($subdomain, ['www', 'api'], true)) {
            return null;
        }

        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $subdomain)) {
            return null;
        }

        return $subdomain;
    }

    private function disableTenantFilter(): void
    {
        $filters = $this->entityManager->getFilters();

        if ($filters->isEnabled('tenant')) {
            $filters->disable('tenant');
        }
    }
}
