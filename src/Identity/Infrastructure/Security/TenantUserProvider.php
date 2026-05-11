<?php

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Entity\User;
use App\Identity\Infrastructure\Doctrine\Repository\UserRepository;
use App\Tenancy\Application\TenantContext;
use App\Tenancy\Domain\Entity\Tenant;
use App\Tenancy\Infrastructure\Doctrine\Repository\TenantRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class TenantUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TenantContext $tenantContext,
        private readonly TenantRepository $tenantRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $tenant = $this->tenantContext->getCurrentTenant() ?? $this->resolveTenantFromRequest();

        if ($tenant === null) {
            throw new UserNotFoundException('No tenant resolved for this request.');
        }

        $em = $this->userRepository->getEntityManager();
        $filters = $em->getFilters();
        $filterEnabled = $filters->isEnabled('tenant');

        if ($filterEnabled) {
            $filters->disable('tenant');
        }

        $user = $this->userRepository->findOneByEmailAndTenant($identifier, $tenant);

        if ($filterEnabled) {
            $filters->enable('tenant')->setParameter('tenant_id', $tenant->getId());
        }

        if ($user === null) {
            $exception = new UserNotFoundException(sprintf('User "%s" was not found for the current tenant.', $identifier));
            $exception->setUserIdentifier($identifier);
            throw $exception;
        }

        return $user;
    }

    private function resolveTenantFromRequest(): ?Tenant
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            return null;
        }

        $host = mb_strtolower($request->headers->get('x-forwarded-host') ?: $request->getHttpHost());
        $host = explode(',', $host)[0] ?? $host;
        $host = trim($host);
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = trim($host, '[]');

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || !str_contains($host, '.')) {
            return null;
        }

        $subdomain = explode('.', $host)[0] ?? '';

        if ($subdomain === '' || in_array($subdomain, ['www', 'api'], true)) {
            return null;
        }

        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $subdomain)) {
            return null;
        }

        $tenant = $this->tenantRepository->findOneBySubdomain($subdomain);

        if ($tenant !== null) {
            $this->tenantContext->setCurrentTenant($tenant);
        }

        return $tenant;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, User::class, true);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $this->userRepository->upgradePassword($user, $newHashedPassword);
    }
}
