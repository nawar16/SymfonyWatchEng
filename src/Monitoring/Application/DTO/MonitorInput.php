<?php

namespace App\Monitoring\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class MonitorInput
{
    #[Assert\NotBlank, Assert\Url]
    public string $url;

    #[Assert\NotBlank, Assert\GreaterThanOrEqual(60)]
    public int $frequency;
}
