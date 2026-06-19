<?php

namespace App\Tests\Monitoring\Application\CommandHandler;

use App\Monitoring\Application\Command\CheckEscalationCommand;
use App\Monitoring\Application\EventHandler\NotificationHandler;
use App\Monitoring\Domain\Entity\NotificationRule;
use App\Monitoring\Domain\Event\IncidentCreatedEvent;
use App\Monitoring\Domain\Service\NotificationSenderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class NotificationHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private NotificationSenderInterface $notificationSender;
    private NotificationHandler $handler;
    private MessageBusInterface $commandBus;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->notificationSender = $this->createMock(NotificationSenderInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->handler = new NotificationHandler(
            $this->notificationSender,
            $this->entityManager,
            $this->commandBus
        );
    }


    public function testItDispatchesCommandWhenCooldownIsNotActive(): void
    {
        $incidentId = 100;
        $monitorId = 5;
        $event = new IncidentCreatedEvent($incidentId, $monitorId, '');
        $this->notificationSender->expects($this->once())
            ->method('tryAcquireNotificationCooldown')
            ->with($monitorId)
            ->willReturn(true);
        $ruleRepoMock = $this->createMock(EntityRepository::class);
        $ruleRepoMock->method('findOneBy')->with(['monitorId' => $monitorId])->willReturn(null);
        $this->entityManager->method('getRepository')
            ->with(NotificationRule::class)
            ->willReturn($ruleRepoMock);
        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (CheckEscalationCommand $cmd) use ($incidentId) {
                return $cmd->incidentId === $incidentId && $cmd->currentStepIndex === 0;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->handler->onIncidentCreated($event);
    }

    public function testItAbortsImmediatelyWhenCooldownIsActive(): void
    {
        $incidentId = 100;
        $monitorId = 5;
        $event = new IncidentCreatedEvent($incidentId, $monitorId, '');
        $this->notificationSender->expects($this->once())
            ->method('tryAcquireNotificationCooldown')
            ->with($monitorId)
            ->willReturn(false);
        $this->entityManager->expects($this->never())->method('getRepository');
        $this->commandBus->expects($this->never())->method('dispatch');

        $this->handler->onIncidentCreated($event);
    }
}
