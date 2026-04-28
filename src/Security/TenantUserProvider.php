<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tenant\TenantContext;
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
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $tenant = $this->tenantContext->getCurrentTenant();

        if ($tenant === null) {
            throw new UserNotFoundException('No tenant resolved for this request.');
        }

        $user = $this->userRepository->findOneByEmailAndTenant($identifier, $tenant);

        if ($user === null) {
            $exception = new UserNotFoundException(sprintf('User "%s" was not found for the current tenant.', $identifier));
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $user;
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
