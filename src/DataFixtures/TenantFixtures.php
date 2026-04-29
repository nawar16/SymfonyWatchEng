<?php

namespace App\DataFixtures;

use App\Entity\Tenant;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TenantFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $companyNames = ['tenant1', 'tenant2', 'tenant3', 'tenant4', 'tenant5'];
        foreach ($companyNames as $name) {
            $tenant = new Tenant();
            $tenant->setName($name);
            $tenant->setSubdomain(strtolower($name));
            $manager->persist($tenant);
        }
        $manager->flush();
    }
}
