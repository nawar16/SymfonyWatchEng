<?php


namespace App\Tests\Monitoring\Application\CommandHandler;

use App\Monitoring\Application\Command\CheckEscalationCommand;
use App\Monitoring\Application\CommandHandler\CheckEscalationHandler;
use App\Monitoring\Domain\Entity\EscalationStep;
use App\Monitoring\Domain\Entity\Incident;
use App\Monitoring\Domain\Entity\NotificationRule;
use App\Monitoring\Domain\Service\NotificationSenderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

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

    public function testStepZeroSendsBaseAlertAndSchedulesStepOne(): void
    {
        $incidentId = 123;
        $monitorId = 45;
        $command = new CheckEscalationCommand($incidentId, 0);        
        $incidentMock = $this->createMock(Incident::class);
        $incidentMock->method('getId')->willReturn($incidentId);
        $incidentMock->method('getMonitorId')->willReturn($monitorId);
        $incidentMock->method('getStatus')->willReturn('active');
        $incidentMock->method('getLastError')->willReturn('Connection timed out');
        $incidentRepo = $this->createMock(EntityRepository::class);
        $incidentRepo->method('find')->with($incidentId)->willReturn($incidentMock);
        $ruleMock = $this->createMock(NotificationRule::class);
        $ruleMock->method('getChannels')->willReturn(['slack', 'email']);
        $ruleRepo = $this->createMock(EntityRepository::class);
        $ruleRepo->method('findOneBy')->with(['monitorId' => $monitorId])->willReturn($ruleMock);

        $stepMock = $this->createMock(EscalationStep::class);
        $stepMock->method('getEscalateAfterMinutes')->willReturn(10);
        $stepRepo = $this->createMock(EntityRepository::class);
        $stepRepo->method('findOneBy')->with(
            ['monitorId' => $monitorId],
            ['escalateAfterMinutes' => 'ASC']
        )->willReturn($stepMock);
        $this->entityManager->method('getRepository')->willReturnMap([
            [Incident::class, $incidentRepo],
            [NotificationRule::class, $ruleRepo],
            [EscalationStep::class, $stepRepo],
        ]);
        $this->notificationSender->expects($this->once())
            ->method('sendIncidentAlert')
            ->with($incidentId, $monitorId, 'Monitor down: Connection timed out');

        //index 1 gets queued with 10 mins delay
        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (CheckEscalationCommand $cmd) use ($incidentId) {
                    return $cmd->incidentId === $incidentId && $cmd->currentStepIndex === 1;
                }),
                $this->callback(function (array $stamps) {
                    /** @var DelayStamp $delayStamp */
                    $delayStamp = $stamps[0] ?? null;
                    return $delayStamp instanceof DelayStamp && $delayStamp->getDelay() === 600000;
                })
            )->willReturn(new Envelope(new \stdClass()));

        $this->handler->__invoke($command);
    }

}
