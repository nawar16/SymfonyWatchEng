<?php

namespace App\Shared\Infrastructure\DataFixtures;

use App\Identity\Domain\Entity\User;
use App\Tenancy\Domain\Entity\Tenant;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher) 
    {}

    public function load(ObjectManager $manager): void
    {
        $companyNames = ['tenant1', 'tenant2', 'tenant3', 'tenant4', 'tenant5'];

        foreach ($companyNames as $name) {
            /** @var Tenant $tenant */
            $tenant = $this->getReference(TenantFixtures::TENANT_REF_PREFIX . $name, Tenant::class);
            $user = new User();
            $user->setTenant($tenant);
            $user->setEmail("admin@{$name}.com");
            $user->setRoles(['ROLE_ADMIN']);
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
            $user->setPassword($hashedPassword);
            $manager->persist($user);
        }

        $manager->flush();
    }
    public function getDependencies(): array
    {
        return [
            TenantFixtures::class,
        ];
    }
}
