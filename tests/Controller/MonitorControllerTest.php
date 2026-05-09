<?php

namespace App\Tests\Controller;

use App\Tenancy\Domain\Entity\Tenant;
use App\Identity\Domain\Entity\User;
use App\Monitoring\Domain\Entity\Monitor;
use App\Tenancy\Application\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class MonitorControllerTest extends WebTestCase
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
            $this->entityManager->getClassMetadata(Monitor::class),
        ];
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        
        $this->client->disableReboot();
    }

    public function testCreateMonitorSuccessfully(): void
    {
        $tenant = $this->createTenant('Acme Corp', 'acme');
        $user = $this->createUser('admin@acme.com', $tenant);
        $this->tenantContextMock->method('getCurrentTenant')->willReturn($tenant);
        $token = $this->jwtManager->create($user);
        $this->client->request(
            'POST',
            '/api/monitors',
            [],
            [],
            [
                'HTTP_HOST' => 'acme.localhost',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'url' => 'https://google.com',
                'frequency' => 60
            ])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('https://google.com', $data['url']);
    }

    public function testCannotCreateDuplicateMonitor(): void
    {
        $tenant = $this->createTenant('Acme Corp', 'acme');
        $user = $this->createUser('admin@acme.com', $tenant);
        $this->tenantContextMock->method('getCurrentTenant')->willReturn($tenant);
        $monitor = new Monitor('https://google.com', 60, $tenant);
        $this->entityManager->persist($monitor);
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);
        
        $this->client->request(
            'POST',
            '/api/monitors',
            [],
            [],
            [
                'HTTP_HOST' => 'acme.localhost',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['url' => 'https://google.com', 'frequency' => 60])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertStringContainsString('already being monitored', $this->client->getResponse()->getContent());
    }

    public function testListOnlyReturnsTenantsOwnMonitors(): void
    {
        $tenantA = $this->createTenant('Tenant A', 'a');
        $userA = $this->createUser('user@a.com', $tenantA);
        $monA = new Monitor('https://site-a.com', 60, $tenantA);
        $this->entityManager->persist($monA);

        $tenantB = $this->createTenant('Tenant B', 'b');
        $monB = new Monitor('https://site-b.com', 60, $tenantB);
        $this->entityManager->persist($monB);
        
        $this->entityManager->flush();

        //for A
        $this->tenantContextMock->method('getCurrentTenant')->willReturn($tenantA);
        $token = $this->jwtManager->create($userA);

        $this->client->request('GET', '/api/monitors', [], [], [
            'HTTP_HOST' => 'a.localhost',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $data);
        self::assertSame('https://site-a.com', $data[0]['url']);
    }

    public function testDeleteUnauthorizedMonitorFails(): void
    {
        $tenantA = $this->createTenant('Tenant A', 'a');
        $userA = $this->createUser('user@a.com', $tenantA);
        
        $tenantB = $this->createTenant('Tenant B', 'b');
        $monB = new Monitor('https://site-b.com', 60, $tenantB);
        $this->entityManager->persist($monB);
        $this->entityManager->flush();

        // A tries to delete Tenant B's monitor
        $this->tenantContextMock->method('getCurrentTenant')->willReturn($tenantA);
        $token = $this->jwtManager->create($userA);

        $this->client->request('DELETE', '/api/monitors/' . $monB->getId(), [], [], [
            'HTTP_HOST' => 'a.localhost',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        //403 Forbidden or 404 
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
    private function createTenant(string $name, string $subdomain): Tenant
    {
        $tenant = (new Tenant())->setName($name)->setSubdomain($subdomain);
        $this->entityManager->persist($tenant);
        return $tenant;
    }

    private function createUser(string $email, Tenant $tenant): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setTenant($tenant)
            ->setRoles(['ROLE_USER'])
            ->setPassword('password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }
}
