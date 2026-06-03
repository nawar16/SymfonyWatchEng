<?php

namespace App\Monitor\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "escalation_steps")]
class EscalationStep
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $monitorId;

    #[ORM\Column]
    private int $escalateAfterMinutes; 

    #[ORM\Column]
    private string $channel; 

    #[ORM\Column]
    private string $recipient;

    public function __construct(int $monitorId, int $escalateAfterMinutes, string $channel, string $recipient)
    {
        $this->monitorId = $monitorId;
        $this->escalateAfterMinutes = $escalateAfterMinutes;
        $this->channel = $channel;
        $this->recipient = $recipient;
    }

    public function getEscalateAfterMinutes(): int { return $this->escalateAfterMinutes; }
    public function getChannel(): string { return $this->channel; }
    public function getRecipient(): string { return $this->recipient; }
}
