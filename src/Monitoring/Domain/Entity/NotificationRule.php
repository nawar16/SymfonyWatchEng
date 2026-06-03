<?php

namespace App\Monitor\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "notification_rules")]
class NotificationRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $monitorId;

    #[ORM\Column(type: "json")]
    private array $channels = []; //current available email,slack,discord

    #[ORM\Column]
    private int $delayMinutes = 0; 

    #[ORM\Column]
    private bool $onlyBusinessHours = false;

    public function __construct(int $monitorId, array $channels, int $delayMinutes = 0, bool $onlyBusinessHours = false)
    {
        $this->monitorId = $monitorId;
        $this->channels = $channels;
        $this->delayMinutes = $delayMinutes;
        $this->onlyBusinessHours = $onlyBusinessHours;
    }

    public function getChannels(): array { return $this->channels; }
    public function getDelayMinutes(): int { return $this->delayMinutes; }
    public function isOnlyBusinessHours(): bool { return $this->onlyBusinessHours; }
}
