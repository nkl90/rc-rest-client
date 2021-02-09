<?php

namespace Nkl\RocketChatRestClient\Exception;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandClientException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class DuplicateChannelNameException extends CommandClientException
{
    public function __construct($message, CommandInterface $command, \Exception $previous = null, RequestInterface $request = null, ResponseInterface $response = null)
    {
        parent::__construct($message, $command, $previous, $request, $response);
    }
}
