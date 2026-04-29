<?php

namespace App\Controller;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\TenantRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantRepository $tenantRepository,
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
        $subdomain = isset($payload['subdomain']) ? $this->normalizeSubdomain((string) $payload['subdomain']) : '';
        $tenantName = isset($payload['tenant_name']) ? trim((string) $payload['tenant_name']) : '';

        if ($email === '' || $password === '' || $subdomain === '') {
            return new JsonResponse(['error' => 'Email, password, and subdomain are required.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->isValidSubdomain($subdomain)) {
            return new JsonResponse([
                'error' => 'Subdomain must be 3-63 characters and contain only lowercase letters, numbers, and hyphens.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (in_array($subdomain, ['www', 'api', 'admin', 'mail', 'localhost'], true)) {
            return new JsonResponse(['error' => 'This subdomain is reserved.'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->tenantRepository->findOneBySubdomain($subdomain) !== null) {
            return new JsonResponse(['error' => 'Subdomain is already taken.'], Response::HTTP_CONFLICT);
        }

        $tenant = (new Tenant())
            ->setName($tenantName !== '' ? $tenantName : $subdomain)
            ->setSubdomain($subdomain);

        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_USER'])
            ->setTenant($tenant);

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        try {
            $this->entityManager->persist($tenant);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse(['error' => 'Subdomain or email is already registered.'], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(
            [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'tenant' => [
                    'id' => $tenant->getId(),
                    'name' => $tenant->getName(),
                    'subdomain' => $tenant->getSubdomain(),
                ],
            ],
            Response::HTTP_CREATED,
        );
    }

    private function normalizeSubdomain(string $subdomain): string
    {
        $subdomain = mb_strtolower(trim($subdomain));
        $subdomain = preg_replace('/[^a-z0-9-]+/', '-', $subdomain) ?? '';

        return trim($subdomain, '-');
    }

    private function isValidSubdomain(string $subdomain): bool
    {
        return preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/', $subdomain) === 1;
    }
}
