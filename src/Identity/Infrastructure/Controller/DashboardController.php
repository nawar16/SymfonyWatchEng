<?php

namespace App\Identity\Infrastructure\Controller;

use App\Tenancy\Application\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/api/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(TenantContext $tenantContext): JsonResponse
    {
        $user = $this->getUser(); 
        $tenant = $tenantContext->getCurrentTenant();

        return $this->json([
            'message' => 'Welcome to your dashboard!',
            'tenant' => [
                'id' => $tenant->getId(),
                'name' => $tenant->getName(),
                'subdomain' => $tenant->getSubdomain(),
            ],
            'user' => [
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}
