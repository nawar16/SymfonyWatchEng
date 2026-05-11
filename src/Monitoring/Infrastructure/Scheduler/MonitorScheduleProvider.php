<?php

namespace App\Monitoring\Infrastructure\Scheduler;

use App\Monitoring\Application\Command\CheckMonitorCommand;
use App\Monitoring\Infrastructure\Doctrine\Repository\MonitorRepository;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Generator\MessageGenerator;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\RecurringMessage;

#[AsSchedule('monitor_heartbeat')]
class MonitorScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private MonitorRepository $monitorRepository
    ) {}

    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::every('1 minute', new class($this->monitorRepository) implements MessageGenerator {
                public function __construct(private MonitorRepository $repo) {}
                public function getMessages(): iterable
                {
                    //TODO: we can add isActive filter herre to increase effeciency and filter unactive monitors
                    $monitors = $this->repo->findAll();

                    foreach ($monitors as $monitor) {
                        yield new CheckMonitorCommand($monitor->getId());
                    }
                }
            })
        );
    }
}
