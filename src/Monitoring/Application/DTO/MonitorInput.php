<?php

namespace App\Monitoring\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class MonitorInput
{
    #[Assert\NotBlank]
    #[Assert\Url(message: "The URL '{{ value }}' is not a valid URL.")]
    public string $url;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\GreaterThanOrEqual(30, message: "Minimum frequency is 30 seconds.")]
    public int $frequency;
}
