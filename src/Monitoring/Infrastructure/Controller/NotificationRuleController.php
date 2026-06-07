<?php

namespace App\Monitoring\Infrastructure\Controller;

use App\Monitoring\Domain\Entity\NotificationRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/monitors/{monitorId}/notification-rule', name: 'api_monitor_notification_rule', methods: ['POST', 'PUT'])]
class NotificationRuleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {    
        $this->entityManager = $entityManager;
    }

    public function __invoke(int $monitorId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }
        $rule = $this->entityManager->getRepository(NotificationRule::class)
            ->findOneBy(['monitorId' => $monitorId]);
        if (!$rule) {
            $rule = new NotificationRule($monitorId, $data['channels'] ?? ['email']);
            
            $reflection = new \ReflectionProperty(NotificationRule::class, 'monitorId');
            $reflection->setAccessible(true);
            $reflection->setValue($rule, $monitorId);
        }
 
        $rule->setChannels($data['channels'] ?? ['email']);
        $rule->setDelayMinutes((int)($data['delayMinutes'] ?? 0));
        $rule->setOnlyBusinessHours((bool)($data['isOnlyBusinessHours'] ?? false));
        $this->entityManager->persist($rule);
        $this->entityManager->flush();
        return new JsonResponse([
            'message' => 'Notification base rules updated successfully.',
            'monitorId' => $monitorId
        ], Response::HTTP_OK);
    }
}
