<?php
/**
 * This file is a part of "comely-io/cache" package.
 * https://github.com/comely-io/io/cache"
 *
 * Copyright (c) Furqan A. Siddiqui <hello@furqansiddiqui.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit following link:
 * https://github.com/comely-io/io/cache/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Comely\Cache\Store;

use Comely\Cache\Exception\CacheException;
use Comely\Cache\Exception\CacheOpException;
use Comely\Cache\Exception\ConnectionException;
use Comely\Cache\Servers\CacheServer;

/**
 * Class Redis
 * @package Comely\Cache\Store
 */
class Redis extends AbstractCacheStore
{
    public const ENGINE = "redis";

    /** @var CacheServer */
    protected $server;
    /** @var int|null */
    private $timeOut;
    /** @var null|resource */
    private $sock;

    /**
     * Redis constructor.
     * @param CacheServer $server
     * @throws ConnectionException
     */
    public function __construct(CacheServer $server)
    {
        $this->timeOut = $server->timeOut ?? 1;
        parent::__construct($server);
        $this->connect();
    }

    /**
     * @throws ConnectionException
     */
    public function connect(): void
    {
        // Establish connection
        $errorNum = 0;
        $errorMsg = "";
        $socket = stream_socket_client(
            sprintf('%s:%d', $this->server->hostname, $this->server->port),
            $errorNum,
            $errorMsg,
            $this->timeOut
        );

        // Connected?
        if (!is_resource($socket)) {
            throw new ConnectionException($errorMsg, $errorNum);
        }

        $this->sock = $socket;
        stream_set_timeout($this->sock, $this->timeOut);
    }

    /**
     * @return void
     */
    public function disconnect(): void
    {
        if ($this->sock) {
            try {
                $this->send("QUIT");
            } catch (CacheException $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
            }
        }

        $this->sock = null;
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        if ($this->sock) {
            $timedOut = @stream_get_meta_data($this->sock)["timed_out"] ?? true;
            if ($timedOut) {
                $this->sock = null;
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @return bool
     * @throws CacheOpException
     * @throws ConnectionException
     */
    public function ping(): bool
    {
        // Check if connected
        if (!$this->isConnected()) {
            throw new ConnectionException('Lost connection with server');
        }

        $ping = $this->send("PING");
        if (!is_string($ping) || strtolower($ping) !== "pong") {
            throw new ConnectionException('Lost connection with server');
        }

        return true;
    }

    /**
     * @param string $key
     * @param $value
     * @param int|null $ttl
     * @return bool
     * @throws CacheOpException
     * @throws ConnectionException
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $query = is_int($ttl) && $ttl > 0 ?
            sprintf('SETEX %s %d "%s"', $key, $ttl, $value) :
            sprintf('SET %s "%s"', $key, $value);

        $exec = $this->send($query);
        if ($exec !== "OK") {
            throw new CacheOpException('Failed to store data/object on REDIS server');
        }

        return true;
    }

    /**
     * @param string $key
     * @return bool|mixed
     * @throws CacheOpException
     * @throws ConnectionException
     */
    public function get(string $key)
    {
        return $this->send(sprintf('GET %s', $key));
    }

    /**
     * @param string $key
     * @return bool
     * @throws CacheOpException
     * @throws ConnectionException
     */
    public function has(string $key): bool
    {
        return $this->send(sprintf('EXISTS %s', $key)) === 1 ? true : false;
    }

    /**
     * @param string $key
     * @return bool
     * @throws CacheOpException
     * @throws ConnectionException
     */
    public function delete(string $key): bool
    {
        return $this->send(sprintf('DEL %s', $key)) === 1 ? true : false;
    }

    /**
     * @return bool
     * @throws CacheOpException
     * @throws ConnectionException
     */
    public function flush(): bool
    {
        return $this->send('FLUSHALL');
    }

    /**
     * @param string $command
     * @return bool|mixed
     * @throws CacheOpException
     * @throws ConnectionException
     */
    private function send(string $command)
    {
        if (!$this->sock) {
            throw new ConnectionException('Not connected to any server');
        }

        $command = trim($command);
        if (strtolower($command) == "disconnect") {
            return @fclose($this->sock);
        }

        $write = fwrite($this->sock, $this->command($command));
        if ($write === false) {
            throw new CacheOpException(sprintf('Failed to send "%1$s" command', explode(" ", $command)[0]));
        }

        return $this->response();
    }

    /**
     * @param string $command
     * @return string
     */
    private function command(string $command): string
    {
        $parts = str_getcsv($command, " ", '"');
        $prepared = "*" . count($parts) . "\r\n";
        foreach ($parts as $part) {
            $prepared .= "$" . strlen($part) . "\r\n" . $part . "\r\n";
        }

        return $prepared;
    }

    /**
     * @return bool|int|string|null
     * @throws CacheOpException
     */
    private function response()
    {
        // Get response from stream
        $response = fgets($this->sock);
        if (!is_string($response)) {
            $timedOut = @stream_get_meta_data($this->sock)["timed_out"] ?? null;
            if ($timedOut === true) {
                throw new CacheOpException('Redis stream has timed out');
            }

            throw new CacheOpException('No response received from server');
        }

        // Prepare response for parsing
        $response = trim($response);
        $responseType = substr($response, 0, 1);
        $data = substr($response, 1);

        // Check response
        switch ($responseType) {
            case "-": // Error
                throw new CacheOpException(substr($data, 4));
                break;
            case "+": // Simple String
                return $data;
            case ":": // Integer
                return intval($data);
            case "$": // Bulk String
                $bytes = intval($data);
                if ($bytes > 0) {
                    $data = stream_get_contents($this->sock, $bytes + 2);
                    if (!is_string($data)) {
                        throw new CacheOpException('Failed to read REDIS bulk-string response');
                    }

                    return trim($data); // Return trimmed
                } elseif ($bytes === 0) {
                    return ""; // Empty String
                } elseif ($bytes === -1) {
                    return null; // NULL
                } else {
                    throw new CacheOpException('Invalid number of REDIS response bytes');
                }
        }

        throw new CacheOpException('Unexpected response from REDIS server');
    }
}