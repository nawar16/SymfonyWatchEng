<?php

namespace App\Identity\Infrastructure\Security;

use App\Entity\User;
use App\Tenancy\Application\TenantContext;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $currentTenant = $this->tenantContext->getCurrentTenant();
        $userTenant = $user->getTenant();

        if ($currentTenant === null || $userTenant === null || $currentTenant->getId() !== $userTenant->getId()) {
            throw new BadCredentialsException('Invalid tenant credentials.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
