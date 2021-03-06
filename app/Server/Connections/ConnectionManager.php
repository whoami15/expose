<?php

namespace App\Server\Connections;

use App\Contracts\ConnectionManager as ConnectionManagerContract;
use App\Contracts\SubdomainGenerator;
use App\Http\QueryParameters;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;

class ConnectionManager implements ConnectionManagerContract
{
    /** @var array */
    protected $connections = [];

    /** @var array */
    protected $httpConnections = [];

    /** @var SubdomainGenerator */
    protected $subdomainGenerator;

    /** @var LoopInterface */
    protected $loop;

    public function __construct(SubdomainGenerator $subdomainGenerator, LoopInterface $loop)
    {
        $this->subdomainGenerator = $subdomainGenerator;
        $this->loop = $loop;
    }

    public function limitConnectionLength(ControlConnection $connection, int $maximumConnectionLength)
    {
        if ($maximumConnectionLength === 0) {
            return;
        }

        $connection->setMaximumConnectionLength($maximumConnectionLength);

        $this->loop->addTimer($maximumConnectionLength * 60, function () use ($connection) {
            $connection->socket->close();
        });
    }

    public function storeConnection(string $host, ?string $subdomain, ConnectionInterface $connection): ControlConnection
    {
        $clientId = (string) uniqid();

        $connection->client_id = $clientId;

        $storedConnection = new ControlConnection(
            $connection,
            $host,
            $subdomain ?? $this->subdomainGenerator->generateSubdomain(),
            $clientId,
            $this->getAuthTokenFromConnection($connection)
        );

        $this->connections[] = $storedConnection;

        return $storedConnection;
    }

    public function storeHttpConnection(ConnectionInterface $httpConnection, $requestId): HttpConnection
    {
        $this->httpConnections[$requestId] = new HttpConnection($httpConnection);

        return $this->httpConnections[$requestId];
    }

    public function getHttpConnectionForRequestId(string $requestId): ?HttpConnection
    {
        return $this->httpConnections[$requestId] ?? null;
    }

    public function removeControlConnection($connection)
    {
        if (isset($connection->request_id)) {
            if (isset($this->httpConnections[$connection->request_id])) {
                unset($this->httpConnections[$connection->request_id]);
            }
        }

        if (isset($connection->client_id)) {
            $clientId = $connection->client_id;
            $this->connections = collect($this->connections)->reject(function ($connection) use ($clientId) {
                return $connection->client_id == $clientId;
            })->toArray();
        }
    }

    public function findControlConnectionForSubdomain($subdomain): ?ControlConnection
    {
        return collect($this->connections)->last(function ($connection) use ($subdomain) {
            return $connection->subdomain == $subdomain;
        });
    }

    public function findControlConnectionForClientId(string $clientId): ?ControlConnection
    {
        return collect($this->connections)->last(function ($connection) use ($clientId) {
            return $connection->client_id == $clientId;
        });
    }

    public function getConnections(): array
    {
        return $this->connections;
    }

    protected function getAuthTokenFromConnection(ConnectionInterface $connection): string
    {
        return QueryParameters::create($connection->httpRequest)->get('authToken');
    }

    public function getConnectionsForAuthToken(string $authToken): array
    {
        return collect($this->connections)
            ->filter(function ($connection) use ($authToken) {
                return $connection->authToken === $authToken;
            })
            ->map(function ($connection) {
                return $connection->toArray();
            })
            ->toArray();
    }
}
