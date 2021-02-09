<?php
declare(strict_types=1);

namespace Nkl\RocketChatRestClient\Test;


use Nkl\RocketChatRestClient\StaticClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

class ClientInitTest extends TestCase
{
    public function testInitialization()
    {
        $client = StaticClient::create();
        $command = $client->getCommand(StaticClient::METHOD_USERS_INFO, [
            'username' => $_ENV['RC_ADMIN_USER'],
        ]);
        dump($client->execute($command)->toArray());
    }
}
