<?php

namespace App\Tests\Controller;

use App\Tenancy\Domain\Entity\Tenant;
use App\Identity\Domain\Entity\User;
use App\Tenancy\Application\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class DashboardControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private JWTTokenManagerInterface $jwtManager;
    private EntityManagerInterface $entityManager;
    private TenantContext $tenantContextMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        $this->tenantContextMock = $this->createMock(TenantContext::class);
        static::getContainer()->set(TenantContext::class, $this->tenantContextMock);
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = [
            $this->entityManager->getClassMetadata(Tenant::class),
            $this->entityManager->getClassMetadata(User::class),
        ];
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $this->client->disableReboot();
    }

    public function testDashboardRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/dashboard');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testUserCanAccessOwnTenantDashboard(): void
    {
        $tenant = (new Tenant())
            ->setName('Tenant One')
            ->setSubdomain('tenant-one');
        $this->entityManager->persist($tenant);
        $user = (new User())
            ->setEmail('user@example.com')
            ->setTenant($tenant)
            ->setRoles(['ROLE_USER'])
            ->setPassword('hashedpassword');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->tenantContextMock->method('getCurrentTenant')->willReturn($tenant);
        $token = $this->jwtManager->create($user);
        $this->client->request(
            'GET',
            '/api/dashboard',
            [],
            [],
            [
                'HTTP_HOST' => 'tenant-one.localhost',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true);
        self::assertSame('Welcome to your dashboard!', $payload['message']);
        self::assertSame('tenant-one', $payload['tenant']['subdomain']);
        self::assertSame('user@example.com', $payload['user']['email']);
    }

    public function testUserCannotAccessDashboardOfDifferentTenant(): void
    {
        $tenantA = (new Tenant())
            ->setName('Tenant A')
            ->setSubdomain('tenant-a');
        $this->entityManager->persist($tenantA);
        $userA = (new User())
            ->setEmail('usera@example.com')
            ->setTenant($tenantA)
            ->setRoles(['ROLE_USER'])
            ->setPassword('hashedpassword');
        $this->entityManager->persist($userA);

        $tenantB = (new Tenant())
            ->setName('Tenant B')
            ->setSubdomain('tenant-b');
        $this->entityManager->persist($tenantB);
        $this->entityManager->flush();
        $this->tenantContextMock->method('getCurrentTenant')->willReturn($tenantB);
        $token = $this->jwtManager->create($userA);

        $this->client->request(
            'GET',
            '/api/dashboard',
            [],
            [],
            [
                'HTTP_HOST' => 'tenant-b.localhost',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}

