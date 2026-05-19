<?php

namespace App\Monitoring\Application\CommandHandler;

use App\Monitoring\Application\Command\TriggerMonitorChecksCommand;
use App\Monitoring\Application\Command\CheckMonitorCommand;
use App\Monitoring\Infrastructure\Doctrine\Repository\MonitorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;


#[AsMessageHandler]
class TriggerMonitorChecksHandler {
    private MonitorRepository $monitorRepository;
    private EntityManagerInterface $entityManager;
    private LockFactory $lockFactory;
    private MessageBusInterface $messageBus;
    
    public function __construct(
        MonitorRepository $monitorRepository,
        EntityManagerInterface $entityManager,
        LockFactory $lockFactory,
        MessageBusInterface $messageBus) 
    {

        $this->monitorRepository = $monitorRepository;
        $this->entityManager =$entityManager;
        $this->lockFactory =$lockFactory;
        $this->messageBus =$messageBus;
    }
    public function __invoke(TriggerMonitorChecksCommand $command): void
    {
        $dueMonitors = $this->monitorRepository->findDueMonitors(1000);
        foreach ($dueMonitors as $monitor) {
            $lock = $this->lockFactory->createLock(
                sprintf('lock:monitor:%d', $monitor->getId()),
                55.0
            );

            if ($lock->acquire()) {  
                $monitor->calculateNextCheck();
                $this->messageBus->dispatch(new CheckMonitorCommand($monitor->getId()));
            }
        }
        $this->entityManager->flush();
    }
}
