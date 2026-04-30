<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class DashboardControllerTest extends WebTestCase
{
    public function testDashboardRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/dashboard');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
