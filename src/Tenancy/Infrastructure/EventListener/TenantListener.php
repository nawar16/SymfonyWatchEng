<?php

namespace App\Tenancy\Infrastructure\EventListener;

use App\Entity\Tenant;
use App\Repository\TenantRepository;
use App\Tenancy\Application\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 100)]
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
        $host = mb_strtolower($this->resolveRequestHost($request));
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

    private function resolveRequestHost(\Symfony\Component\HttpFoundation\Request $request): string
    {
        $forwardedHost = $request->headers->get('x-forwarded-host');

        if (is_string($forwardedHost) && $forwardedHost !== '') {
            // Keep the first host if multiple proxies appended values.
            $hosts = explode(',', $forwardedHost);
            $firstHost = trim($hosts[0] ?? '');

            if ($firstHost !== '') {
                return $firstHost;
            }
        }

        return $request->getHttpHost();
    }

    private function extractSubdomain(string $host): ?string
    {
        $host = trim($host);
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
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
