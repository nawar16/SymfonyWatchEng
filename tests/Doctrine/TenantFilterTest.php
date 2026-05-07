<?php

namespace App\Tests\Doctrine;

use App\Entity\Tenant;
use App\Entity\User;
use App\Tenancy\Infrastructure\EventListener\TenantListener;
use App\Tenancy\Application\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\After;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class TenantFilterTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private TenantListener $tenantListener;

    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->tenantListener = $container->get(TenantListener::class);
        $this->tenantContext = $container->get(TenantContext::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = [
            $this->entityManager->getClassMetadata(Tenant::class),
            $this->entityManager->getClassMetadata(User::class),
        ];

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $tenantOne = (new Tenant())
            ->setName('Tenant One')
            ->setSubdomain('tenant1');

        $tenantTwo = (new Tenant())
            ->setName('Tenant Two')
            ->setSubdomain('tenant2');

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

        $this->tenantContext->setCurrentTenant(null);
    }

    public function testTenantContextIsResolvedFromHostname(): void
    {
        $request = Request::create('http://tenant1.test/');
        $event = new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->tenantListener->onKernelRequest($event);

        $tenant = $this->tenantContext->getCurrentTenant();

        self::assertNotNull($tenant);
        self::assertSame('tenant1', $tenant->getSubdomain());

        $filter = $this->entityManager->getFilters()->getFilter('tenant');

        self::assertSame("'".$tenant->getId()."'", $filter->getParameter('tenant_id'));
    }

    public function testUnknownSubdomainReturnsTenantNotFoundResponse(): void
    {
        $request = Request::create('http://missing.test/');
        $event = new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->tenantListener->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        self::assertSame(404, $event->getResponse()?->getStatusCode());
        self::assertStringContainsString('Tenant not found', $event->getResponse()?->getContent() ?: '');
        self::assertNull($this->tenantContext->getCurrentTenant());
    }
}
