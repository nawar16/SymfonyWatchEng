<?php

namespace App\Tests\Monitoring\Application\CommandHandler;

use App\Monitoring\Application\Command\CheckMonitorCommand;
use App\Monitoring\Application\Command\TriggerMonitorChecksCommand;
use App\Monitoring\Application\CommandHandler\TriggerMonitorChecksHandler;
use App\Monitoring\Domain\Entity\Monitor;
use App\Monitoring\Infrastructure\Doctrine\Repository\MonitorRepository;
use App\Tenancy\Domain\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class TriggerMonitorChecksHandlerTest extends KernelTestCase
{
    /** @var MockObject&MonitorRepository */
    private MockObject $monitorRepository;
    /** @var MockObject&EntityManagerInterface */
    private MockObject $entityManager;
    /** @var MockObject&LockFactory */
    private MockObject $lockFactory;
    /** @var MockObject&MessageBusInterface */
    private MockObject $messageBus;
    private TriggerMonitorChecksHandler $handler;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->monitorRepository = $this->createMock(MonitorRepository::class);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->handler = new TriggerMonitorChecksHandler(
            $this->monitorRepository,
            $this->entityManager,
            $this->lockFactory,
            $this->messageBus
        );
    }

    public function testAcquiredLockDispatchesCheckMonitorCommandAndUpdatesNextCheckTime(): void
    {
        $monitor = $this->createMonitorWithId(123);
        $previousNextCheckAt = new \DateTimeImmutable('2026-05-18 00:00:00');
        $this->setPrivateProperty($monitor, 'nextCheckAt', $previousNextCheckAt);
        
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);// get the race condition lock
        $this->monitorRepository->expects($this->once())
            ->method('findDueMonitors')
            ->with(1000)
            ->willReturn([$monitor]);
        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->with('lock:monitor:123', 55.0)
            ->willReturn($lock);
        //child async check command is sent to the message queue
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(static function (CheckMonitorCommand $command): bool {
                return $command->monitorId === 123;
            }))
            ->willReturn(new Envelope(new CheckMonitorCommand(123)));
        $this->entityManager->expects($this->once())
            ->method('flush');
        ($this->handler)(new TriggerMonitorChecksCommand());

        self::assertNotNull($monitor->getNextCheckAt());
        self::assertGreaterThan(
            $previousNextCheckAt->getTimestamp(),
            $monitor->getNextCheckAt()->getTimestamp()
        );
    }

    public function testFailedLockDoesNotDispatchCheckMonitorCommand(): void
    {
        $monitor = $this->createMonitorWithId(456);
        $previousNextCheckAt = new \DateTimeImmutable('2026-05-18 00:00:00');
        $this->setPrivateProperty($monitor, 'nextCheckAt', $previousNextCheckAt);
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->willReturn(false);//lose the lock
        $this->monitorRepository->expects($this->once())
            ->method('findDueMonitors')
            ->with(1000)
            ->willReturn([$monitor]);
        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->with('lock:monitor:456', 55.0)
            ->willReturn($lock);
        $this->messageBus->expects($this->never())
            ->method('dispatch');
        $this->entityManager->expects($this->once())
            ->method('flush');

        ($this->handler)(new TriggerMonitorChecksCommand());

        self::assertSame($previousNextCheckAt, $monitor->getNextCheckAt());
    }
    private function createMonitorWithId(int $id): Monitor
    {
        $tenant = (new Tenant())
            ->setName('Acme')
            ->setSubdomain('acme');

        $monitor = new Monitor('https://example.com', 60, $tenant);
        $this->setPrivateProperty($monitor, 'id', $id);
        return $monitor;
    }
    private function setPrivateProperty(object $object, string $propertyName, mixed $value): void
    {
        $reflectionProperty = new \ReflectionProperty($object, $propertyName);
        $reflectionProperty->setValue($object, $value);
    }
}
