<?php

namespace App\Tests\Controller;

use App\Tenancy\Domain\Entity\Tenant;
use App\Identity\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = [
            $this->entityManager->getClassMetadata(Tenant::class),
            $this->entityManager->getClassMetadata(User::class),
        ];

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->client->disableReboot();
    }

    public function testRegisterCreatesTenantAndOwnerUser(): void
    {
        $this->client->request(
            'POST',
            '/api/register',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'subdomain' => 'Tenant-One',
                'tenant_name' => 'Tenant One',
                'email' => 'User@Example.com',
                'password' => 'secret123',
            ], JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertJson($response->getContent() ?: '');

        $payload = json_decode($response->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('user@example.com', $payload['email']);
        self::assertSame('tenant-one', $payload['tenant']['subdomain']);
        self::assertSame('Tenant One', $payload['tenant']['name']);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);

        self::assertNotNull($user);
        self::assertSame('tenant-one', $user->getTenant()?->getSubdomain());
        self::assertNotSame('secret123', $user->getPassword());
    }

    public function testRegisterRejectsTenantIdInPayload(): void
    {
        $this->client->request(
            'POST',
            '/api/register',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'subdomain' => 'tenant1',
                'email' => 'user@example.com',
                'password' => 'secret123',
                'tenant_id' => 99,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertStringContainsString('tenant_id cannot be provided', $this->client->getResponse()->getContent() ?: '');
    }

    public function testRegisterRejectsDuplicateSubdomain(): void
    {
        $tenant = (new Tenant())
            ->setName('Tenant One')
            ->setSubdomain('tenant1');

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            '/api/register',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'subdomain' => 'tenant1',
                'email' => 'user@example.com',
                'password' => 'secret123',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertStringContainsString('Subdomain is already taken', $this->client->getResponse()->getContent() ?: '');
    }
}
