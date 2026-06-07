<?php

namespace App\Monitoring\Infrastructure\Controller;

use App\Monitoring\Domain\Entity\EscalationStep;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/monitors/{monitorId}/escalation-steps', name: 'api_monitor_escalation_steps', methods: ['POST', 'PUT'])]
class EscalationStepsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {    
        $this->entityManager = $entityManager;
    }

    public function __invoke(int $monitorId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['steps']) || !is_array($data['steps'])) {
            return new JsonResponse(['error' => 'Invalid timeline configuration array'], Response::HTTP_BAD_REQUEST);
        }

        $existingSteps = $this->entityManager->getRepository(EscalationStep::class)
            ->findBy(['monitorId' => $monitorId]);

        foreach ($existingSteps as $oldStep) {
            $this->entityManager->remove($oldStep);
        }
        foreach ($data['steps'] as $stepData) {
            if (empty($stepData['channel']) || !isset($stepData['escalateAfterMinutes'])) {
                continue; //empty or malformed
            }

            $escalateAfterMinutes = (int)$stepData['escalateAfterMinutes'];
            $channel = strtolower($stepData['channel']);
            $recipient = $stepData['recipient'] ?? 'default';
            $step = new EscalationStep(
                $monitorId,
                $escalateAfterMinutes,
                $channel,
                $recipient
            );

            $reflection = new \ReflectionProperty(EscalationStep::class, 'monitorId');
            $reflection->setAccessible(true);
            $reflection->setValue($step, $monitorId);

            $this->entityManager->persist($step);
        }
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Escalation policy timeline updated successfully.',
            'monitorId' => $monitorId,
            'stepsCount' => count($data['steps'])
        ], Response::HTTP_OK);
    }
}
