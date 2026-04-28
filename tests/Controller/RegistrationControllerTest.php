<?php

namespace App\Tests\Controller;

use App\Entity\Tenant;
use App\Entity\User;
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

        $tenant = (new Tenant())
            ->setName('Tenant One')
            ->setSubdomain('tenant1.test');

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        $this->client->disableReboot();
    }

    public function testRegisterCreatesUserForCurrentTenant(): void
    {
        $this->client->request(
            'POST',
            'http://tenant1.test/api/register',
            [],
            [],
            [
                'HTTP_HOST' => 'tenant1.test',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'email' => 'User@Example.com',
                'password' => 'secret123',
            ], JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertJson($response->getContent() ?: '');

        $payload = json_decode($response->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('user@example.com', $payload['email']);
        self::assertSame('tenant1.test', $payload['tenant']);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);

        self::assertNotNull($user);
        self::assertSame('tenant1.test', $user->getTenant()?->getSubdomain());
        self::assertNotSame('secret123', $user->getPassword());
    }

    public function testRegisterRejectsTenantIdInPayload(): void
    {
        $this->client->request(
            'POST',
            'http://tenant1.test/api/register',
            [],
            [],
            [
                'HTTP_HOST' => 'tenant1.test',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'email' => 'user@example.com',
                'password' => 'secret123',
                'tenant_id' => 99,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertStringContainsString('tenant_id cannot be provided', $this->client->getResponse()->getContent() ?: '');
    }
}
