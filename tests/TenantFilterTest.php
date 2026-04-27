<?php

namespace App\Tests;

use App\Entity\Tenant;
use App\Entity\User;
use App\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\After;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class TenantFilterTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private TenantContext $tenantContext;

    private KernelInterface $kernel;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->tenantContext = $container->get(TenantContext::class);
        $this->kernel = self::$kernel;

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = [
            $this->entityManager->getClassMetadata(Tenant::class),
            $this->entityManager->getClassMetadata(User::class),
        ];

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $tenantOne = (new Tenant())
            ->setName('Tenant One')
            ->setSubdomain('tenant1.test');

        $tenantTwo = (new Tenant())
            ->setName('Tenant Two')
            ->setSubdomain('tenant2.test');

        $this->entityManager->persist($tenantOne);
        $this->entityManager->persist($tenantTwo);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    #[After]
    public function resetDoctrineFilter(): void
    {
        if (!isset($this->entityManager)) {
            return;
        }

        $filters = $this->entityManager->getFilters();

        if ($filters->isEnabled('tenant')) {
            $filters->disable('tenant');
        }

        $this->tenantContext->setTenant(null);
    }

    public function testTenantContextIsResolvedFromHostname(): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $request = Request::create('http://tenant1.test/');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $dispatcher->dispatch($event);

        $tenant = $this->tenantContext->getTenant();

        self::assertNotNull($tenant);
        self::assertSame('tenant1.test', $tenant->getSubdomain());

        $filter = $this->entityManager->getFilters()->getFilter('tenant');

        self::assertSame("'".$tenant->getId()."'", $filter->getParameter('tenant_id'));
    }
}
