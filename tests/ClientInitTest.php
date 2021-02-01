<?php
declare(strict_types=1);

namespace Nkl\RocketChatRestClient\Test;


use Nkl\RocketChatRestClient\StaticClient;
use PHPUnit\Framework\TestCase;

class ClientInitTest extends TestCase
{
    private $config = [
        'entrypoint' => 'https://stage.rc.dev.neural-university.com/api/v1/',
        'admin_credentials_src' => __DIR__ . '/../var/admin_credentials.json',
        'admin_password' => '677c0ec3'
    ];

    public function testInitialization()
    {
        $client = StaticClient::create($this->config);
    }
}
