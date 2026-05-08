<?php

namespace App\Monitoring\Infrastructure\Controller;

use App\Monitoring\Application\DTO\MonitorInput;
use App\Monitoring\Application\Service\CreateMonitorHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/monitors')]
class MonitorController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] MonitorInput $input,
        CreateMonitorHandler $handler
    ): JsonResponse {
        try {
            $monitor = $handler->handle($input);
            return $this->json(['id' => $monitor->getId(), 'url' => $monitor->getUrl()], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
