<?php

namespace App\Shared\Infrastructure\DataFixtures;

use App\Monitoring\Domain\Entity\Monitor;
use App\Tenancy\Domain\Entity\Tenant;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MonitorFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['phase2'];
    }

    public function load(ObjectManager $manager): void
    {
        $companyNames = ['tenant1', 'tenant2', 'tenant3', 'tenant4', 'tenant5'];
        $urls = [
            'https://google.com',
            'https://github.com',
            'https://symfony.com'
        ];
        foreach ($companyNames as $name) {
            /** @var Tenant $tenant */
            $tenant = $this->getReference(TenantFixtures::TENANT_REF_PREFIX . $name, Tenant::class);
            foreach ($urls as $url) {
                $existingMonitor = $manager->getRepository(Monitor::class)->findOneBy([
                    'url' => $url,
                    'tenant' => $tenant
                ]);
                if (!$existingMonitor) {
                    $monitor = new Monitor($url, 60, $tenant);
                    $manager->persist($monitor);
                }
            }
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
