<?php


namespace App\Tests\Monitoring\Application\CommandHandler;

use App\Monitoring\Application\Command\CheckEscalationCommand;
use App\Monitoring\Application\CommandHandler\CheckEscalationHandler;
use App\Monitoring\Domain\Entity\Incident;
use App\Monitoring\Domain\Service\NotificationSenderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class CheckEscalationHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private NotificationSenderInterface $notificationSender;
    private CheckEscalationHandler $handler;
    private MessageBusInterface $commandBus;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->notificationSender = $this->createMock(NotificationSenderInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);

        $this->handler = new CheckEscalationHandler(
            $this->entityManager,
            $this->notificationSender,
            $this->commandBus
        );
    }

    public function testItAbortsWhenIncidentIsResolved(): void
    {
        $incidentId = 123;
        $command = new CheckEscalationCommand($incidentId, 0);

        $incidentMock = $this->createMock(Incident::class);
        $incidentMock->method('getStatus')->willReturn(Incident::STATUS_RESOLVED);

        $repositoryMock = $this->createMock(EntityRepository::class);
        $repositoryMock->method('find')->with($incidentId)->willReturn($incidentMock);

        $this->entityManager->method('getRepository')
            ->with(Incident::class)
            ->willReturn($repositoryMock);

        $this->notificationSender->expects($this->never())->method('sendIncidentAlert');
        $this->notificationSender->expects($this->never())->method('sendEscalationAlert');
        $this->commandBus->expects($this->never())->method('dispatch');

        $this->handler->__invoke($command);
    }
}
