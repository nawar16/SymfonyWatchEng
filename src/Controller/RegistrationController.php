<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantContext $tenantContext,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('tenant_id', $payload)) {
            return new JsonResponse(['error' => 'tenant_id cannot be provided.'], Response::HTTP_BAD_REQUEST);
        }

        $email = isset($payload['email']) ? mb_strtolower(trim((string) $payload['email'])) : '';
        $password = isset($payload['password']) ? (string) $payload['password'] : '';

        if ($email === '' || $password === '') {
            return new JsonResponse(['error' => 'Email and password are required.'], Response::HTTP_BAD_REQUEST);
        }

        $tenant = $this->tenantContext->getCurrentTenant();

        if ($tenant === null) {
            return new JsonResponse(['error' => 'No tenant resolved for this request.'], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $this->userRepository->findOneByEmailAndTenant($email, $tenant);

        if ($existingUser !== null) {
            return new JsonResponse(['error' => 'Email is already registered for this tenant.'], Response::HTTP_CONFLICT);
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_USER'])
            ->setTenant($tenant);

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(
            [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'tenant' => $tenant->getSubdomain(),
            ],
            Response::HTTP_CREATED,
        );
    }
}
