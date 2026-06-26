<?php

namespace App\Tests\Monitoring\Application\CommandHandler;

use App\Monitoring\Domain\Entity\Monitor;
use App\Monitoring\Domain\Entity\HealthCheck;
use App\Monitoring\Domain\Service\PingServiceInterface;
use App\Monitoring\Domain\Service\MonitorStateStoreInterface;
use App\Monitoring\Application\Command\CheckMonitorCommand;
use App\Monitoring\Application\CommandHandler\CheckMonitorHandler;
use App\Monitoring\Infrastructure\Doctrine\Repository\HealthCheckRepository;
use App\Monitoring\Infrastructure\Doctrine\Repository\MonitorRepository;
use App\Monitoring\Infrastructure\Service\IncidentEngine;
use App\Tenancy\Domain\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CheckMonitorHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CheckMonitorHandler $handler;

    /** @var \PHPUnit\Framework\MockObject\MockObject&PingServiceInterface */
    private $pingServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&MonitorStateStoreInterface */
    private $stateStoreMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&IncidentEngine */
    private IncidentEngine $incidentEngineMock;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = [
            $this->entityManager->getClassMetadata(Tenant::class),
            $this->entityManager->getClassMetadata(Monitor::class),
            $this->entityManager->getClassMetadata(HealthCheck::class),
        ];
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $this->pingServiceMock = $this->createMock(PingServiceInterface::class);
        $this->stateStoreMock = $this->createMock(MonitorStateStoreInterface::class);
        $this->incidentEngineMock = $this->createMock(IncidentEngine::class);
        
        $this->handler = new CheckMonitorHandler(
          //  $this->entityManager->getRepository(Monitor::class),
            $container->get(MonitorRepository::class),
            $container->get(HealthCheckRepository::class),
            $this->pingServiceMock,
            $this->stateStoreMock,
            $this->incidentEngineMock
        );
    }

    public function testSuccessfulPingPersistsToDbAndUpdatesRedis(): void
    {
        $tenant = (new Tenant())->setName('Acme')->setSubdomain('acme');
        $this->entityManager->persist($tenant);
        $monitor = new Monitor('https://healthy-site.com', 60, $tenant);
        $this->entityManager->persist($monitor);
        $this->entityManager->flush();
        $this->pingServiceMock->expects($this->once())
            ->method('ping')
            ->with('https://healthy-site.com')
            ->willReturn([
                'status_code' => 200,
                'response_time' => 120,
                'success' => true
            ]);

        $this->stateStoreMock->expects($this->once())
            ->method('updateStatus')
            ->with($monitor->getId(), 'UP', 120, 200);

        $this->incidentEngineMock->expects($this->once())
            ->method('handleSignal')
            ->with($monitor->getId(), true, 200, '');

        $command = new CheckMonitorCommand($monitor->getId());
        ($this->handler)($command);
        

        $healthChecks = $this->entityManager->getRepository(HealthCheck::class)->findBy([
            'monitorId' => $monitor->getId()
        ]);
        self::assertCount(1, $healthChecks);
        self::assertSame(200, $healthChecks[0]->getStatusCode());
        self::assertSame(120, $healthChecks[0]->getResponseTimeMs());
        self::assertTrue($healthChecks[0]->isSuccess());
    }

    public function testFailedPingIncrementsFailuresAndTriggersIncidentOnThreshold(): void
    {
        $tenant = (new Tenant())->setName('Acme')->setSubdomain('acme');
        $this->entityManager->persist($tenant);
        
        $monitor = new Monitor('https://broken-site.com', 60, $tenant);
        $this->entityManager->persist($monitor);
        $this->entityManager->flush();

        //failure
        $this->pingServiceMock->expects($this->once())
            ->method('ping')
            ->willReturn([
                'status_code' => 500,
                'response_time' => 450,
                'success' => false
            ]);
        
        $this->stateStoreMock->expects($this->once())
            ->method('updateStatus')
            ->with($monitor->getId(), 'DOWN', 450, 500);


        // $this->stateStoreMock->expects($this->once())
        //     ->method('incrementFailures')
        //     ->with($monitor->getId())
        //     ->willReturn(3);
        // $this->stateStoreMock->expects($this->once())
        //     ->method('setActiveIncident')
        //     ->with($monitor->getId(), self::callback(function ($incidentData) {
        //         return isset($incidentData['incident_id']) && isset($incidentData['started_at']);
        //     }));

        $this->incidentEngineMock->expects($this->once())
            ->method('handleSignal')
            ->with(
                $monitor->getId(), 
                false, 
                500, 
                'HTTP status check returned code: 500'
        );

        $command = new CheckMonitorCommand($monitor->getId());
        ($this->handler)($command);

        // history log should stored as false
        $healthChecks = $this->entityManager->getRepository(HealthCheck::class)->findAll();
        self::assertCount(1, $healthChecks);
        self::assertFalse($healthChecks[0]->isSuccess());
    }
    public function testFailedPingRegistersExactlyOneSignalWithoutRetryInterference(): void
    {
        $tenant = (new Tenant())->setName('Acme')->setSubdomain('acme');
        $this->entityManager->persist($tenant);
        
        $monitor = new Monitor('https://failing-target.local', 60, $tenant);
        $this->entityManager->persist($monitor);
        $this->entityManager->flush();
    
        $this->pingServiceMock->expects($this->once()) 
            ->method('ping')
            ->willReturn([
                'status_code' => 502,
                'response_time' => 90,
                'success' => false
            ]);
        $this->stateStoreMock->expects($this->once())
            ->method('incrementFailures')
            ->with($monitor->getId())
            ->willReturn(1);
        $command = new CheckMonitorCommand($monitor->getId());
        ($this->handler)($command);
    

        $healthChecks = $this->entityManager->getRepository(HealthCheck::class)->findAll();
        self::assertCount(1, $healthChecks);
        self::assertFalse($healthChecks[0]->isSuccess());
    }

}
