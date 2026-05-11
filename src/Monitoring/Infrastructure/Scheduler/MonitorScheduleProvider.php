<?php

namespace App\Monitoring\Infrastructure\Scheduler;

use App\Monitoring\Application\Command\CheckMonitorCommand;
use App\Monitoring\Infrastructure\Doctrine\Repository\MonitorRepository;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
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
            
        $schedule = new Schedule();
        $monitors = $this->monitorRepository->findAll();
        foreach ($monitors as $monitor) {
            $schedule->add(
                RecurringMessage::every('1 minute', new CheckMonitorCommand($monitor->getId()))
            );
        }
        return $schedule;
        // return (new Schedule())->add(
        //     RecurringMessage::every('1 minute', function() {
        //         $monitors = $this->monitorRepository->findAll();
        //         foreach ($monitors as $monitor) {
        //             yield new CheckMonitorCommand($monitor->getId());
        //         }
        //     })
        // );
    }
}
