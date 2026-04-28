<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $entityManager,
        TenantContext $tenantContext,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $this->json(['error' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('tenant_id', $payload)) {
            return $this->json(['error' => 'tenant_id cannot be provided.'], Response::HTTP_BAD_REQUEST);
        }

        $email = isset($payload['email']) ? mb_strtolower(trim((string) $payload['email'])) : '';
        $password = isset($payload['password']) ? (string) $payload['password'] : '';

        if ($email === '' || $password === '') {
            return $this->json(['error' => 'Email and password are required.'], Response::HTTP_BAD_REQUEST);
        }

        $tenant = $tenantContext->getCurrentTenant();

        if ($tenant === null) {
            return $this->json(['error' => 'No tenant resolved for this request.'], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $userRepository->findOneByEmailAndTenant($email, $tenant);

        if ($existingUser !== null) {
            return $this->json(['error' => 'Email is already registered for this tenant.'], Response::HTTP_CONFLICT);
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_USER'])
            ->setTenant($tenant);

        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(
            [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'tenant' => $tenant->getSubdomain(),
            ],
            Response::HTTP_CREATED,
        );
    }
}
