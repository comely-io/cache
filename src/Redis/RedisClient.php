<?php
/*
 * This file is a part of "comely-io/cache" package.
 * https://github.com/comely-io/cache
 *
 * Copyright (c) Furqan A. Siddiqui <hello@furqansiddiqui.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit following link:
 * https://github.com/comely-io/cache/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Comely\Cache\Redis;

use Comely\Cache\Exception\RedisClientException;
use Comely\Cache\Exception\RedisConnectionException;
use Comely\Cache\Exception\RedisOpException;

/**
 * Class Redis
 * @package Comely\Cache\Store
 */
class RedisClient
{
    /** @var null|resource */
    private $sock = null;

    /**
     * RedisClient constructor.
     * @param string $hostname
     * @param int $port
     * @param int $timeOut
     */
    public function __construct(
        private string $hostname,
        private int $port,
        private int $timeOut = 1
    )
    {
    }

    /**
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            get_called_class(),
            $this->hostname,
            $this->port,
        ];
    }

    /**
     * @return void
     */
    public function __clone(): void
    {
        $this->sock = null;
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            "hostname" => $this->hostname,
            "port" => $this->port,
            "timeOut" => $this->timeOut
        ];
    }

    /**
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->hostname = $data["hostname"];
        $this->port = $data["port"];
        $this->timeOut = $data["timeOut"];
        $this->sock = null;
    }

    /**
     * @throws RedisConnectionException
     */
    public function connect(): void
    {
        // Establish connection
        $errorNum = 0;
        $errorMsg = "";
        $socket = stream_socket_client(
            sprintf('%s:%d', $this->hostname, $this->port),
            $errorNum,
            $errorMsg,
            $this->timeOut
        );

        // Connected?
        if (!is_resource($socket)) {
            throw new RedisConnectionException($errorMsg, $errorNum);
        }

        $this->sock = $socket;
        stream_set_timeout($this->sock, $this->timeOut);
    }

    /**
     * @return string
     */
    public function hostname(): string
    {
        return $this->hostname;
    }

    /**
     * @return int
     */
    public function port(): int
    {
        return $this->port;
    }

    /**
     * @return void
     */
    public function disconnect(): void
    {
        if ($this->isConnected()) {
            try {
                $this->send("QUIT");
            } catch (RedisClientException) {
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
     * @throws RedisConnectionException
     * @throws RedisOpException
     */
    public function ping(): bool
    {
        // Check if connected
        if (!$this->isConnected()) {
            throw new RedisConnectionException('Lost connection with server');
        }

        $ping = $this->send("PING");
        if (!is_string($ping) || strtolower($ping) !== "pong") {
            throw new RedisOpException('Did not receive PONG back');
        }

        return true;
    }

    /**
     * @param string $key
     * @param int|string $value
     * @param int|null $ttl
     * @return bool
     * @throws RedisConnectionException
     * @throws RedisOpException
     */
    public function set(string $key, int|string $value, ?int $ttl = null): bool
    {
        $query = is_int($ttl) && $ttl > 0 ?
            sprintf('SETEX %s %d "%s"', $key, $ttl, $value) :
            sprintf('SET %s "%s"', $key, $value);

        $exec = $this->send($query);
        if ($exec !== "OK") {
            throw new RedisOpException('Failed to store data on REDIS server');
        }

        return true;
    }

    /**
     * @param string $key
     * @return int|string|bool|null
     * @throws RedisConnectionException
     * @throws RedisOpException
     */
    public function get(string $key): int|string|null|bool
    {
        return $this->send(sprintf('GET %s', $key));
    }

    /**
     * @param string $key
     * @return bool
     * @throws RedisConnectionException
     * @throws RedisOpException
     */
    public function has(string $key): bool
    {
        return $this->send(sprintf('EXISTS %s', $key)) === 1;
    }

    /**
     * @param string $key
     * @return bool
     * @throws RedisConnectionException
     * @throws RedisOpException
     */
    public function delete(string $key): bool
    {
        return $this->send(sprintf('DEL %s', $key)) === 1;
    }

    /**
     * @return bool
     * @throws RedisConnectionException
     * @throws RedisOpException
     */
    public function flush(): bool
    {
        return $this->send('FLUSHALL');
    }

    /**
     * @param string $command
     * @return string
     */
    private function prepareCommand(string $command): string
    {
        $parts = str_getcsv($command, " ", '"');
        $prepared = "*" . count($parts) . "\r\n";
        foreach ($parts as $part) {
            $prepared .= "$" . strlen($part) . "\r\n" . $part . "\r\n";
        }

        return $prepared;
    }

    /**
     * @param string $command
     * @return int|string|bool|null
     * @throws RedisConnectionException
     * @throws RedisOpException
     */
    private function send(string $command): int|string|null|bool
    {
        if (!$this->sock) {
            $this->connect();
        }

        $command = trim($command);
        if (strtolower($command) == "disconnect") {
            return @fclose($this->sock);
        }

        $write = fwrite($this->sock, $this->prepareCommand($command));
        if ($write === false) {
            throw new RedisOpException(sprintf('Failed to send "%1$s" command', explode(" ", $command)[0]));
        }

        return $this->response();
    }

    /**
     * @return int|string|null
     * @throws RedisOpException
     */
    private function response(): int|string|null
    {
        // Get response from stream
        $response = fgets($this->sock);
        if (!is_string($response)) {
            $timedOut = @stream_get_meta_data($this->sock)["timed_out"] ?? null;
            if ($timedOut === true) {
                throw new RedisOpException('Redis stream has timed out');
            }

            throw new RedisOpException('No response received from server');
        }

        // Prepare response for parsing
        $response = trim($response);
        $responseType = substr($response, 0, 1);
        $data = substr($response, 1);

        // Check response
        switch ($responseType) {
            case "-": // Error
                throw new RedisOpException(substr($data, 4));
            case "+": // Simple String
                return $data;
            case ":": // Integer
                return intval($data);
            case "$": // Bulk String
                $bytes = intval($data);
                if ($bytes > 0) {
                    $data = stream_get_contents($this->sock, $bytes + 2);
                    if (!is_string($data)) {
                        throw new RedisOpException('Failed to read REDIS bulk-string response');
                    }

                    return trim($data); // Return trimmed
                } elseif ($bytes === 0) {
                    return ""; // Empty String
                } elseif ($bytes === -1) {
                    return null; // NULL
                } else {
                    throw new RedisOpException('Invalid number of REDIS response bytes');
                }
        }

        throw new RedisOpException('Unexpected response from REDIS server');
    }
}
