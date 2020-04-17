<?php

namespace App\Server;

use App\Server\Connections\ConnectionManager;
use App\Server\Connections\HttpRequestConnection;
use App\Server\Messages\ControlMessage;
use App\Server\Messages\MessageFactory;
use App\Server\Messages\TunnelMessage;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use function GuzzleHttp\Psr7\parse_request;

class Expose implements MessageComponentInterface
{
    protected $connectionManager;

    public function __construct(ConnectionManager $connectionManager)
    {
        $this->connectionManager = $connectionManager;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // TODO: Implement onOpen() method.
    }

    public function onClose(ConnectionInterface $conn)
    {
        dump("close connection");
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        // TODO: Implement onError() method.
    }

    public function onMessage(ConnectionInterface $connection, $message)
    {
        $payload = json_decode($message);

        if (json_last_error() === JSON_ERROR_NONE) {
            $message = new ControlMessage($payload, $connection, $this->connectionManager);
            $message->respond();
        } else {
            $message = new TunnelMessage(HttpRequestConnection::wrap($connection, $message), $this->connectionManager);
            $message->respond();
        }
    }
}