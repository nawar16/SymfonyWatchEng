<?php

namespace App\Monitoring\Domain\Service;

interface PingServiceInterface
{
    /**
     * @return array{status_code: int, response_time: int, success: bool}
     */
    public function ping(string $url): array;
}
