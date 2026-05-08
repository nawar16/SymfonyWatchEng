<?php

namespace App\Monitoring\Infrastructure\Controller;

use App\Monitoring\Application\DTO\MonitorInput;
use App\Monitoring\Application\Service\MonitorService;
use App\Monitoring\Domain\Entity\Monitor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/monitors')]
class MonitorController extends AbstractController
{
    public function __construct(
        private readonly MonitorService $service
    ) {}

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] MonitorInput $input): JsonResponse
    {
        $monitor = $this->service->create($input);
        return $this->json($monitor, 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->service->listAll());
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, #[MapRequestPayload] MonitorInput $input): JsonResponse
    {
        return $this->json($this->service->update($id, $input));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->service->delete($id);
        return $this->json(null, 204);
    }
}
