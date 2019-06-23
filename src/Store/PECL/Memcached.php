<?php /** @noinspection PhpComposerExtensionStubsInspection */
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

namespace Comely\Cache\Store\PECL;

use Comely\Cache\Exception\CacheOpException;
use Comely\Cache\Exception\ConnectionException;
use Comely\Cache\Servers\CacheServer;
use Comely\Cache\Store\AbstractCacheStore;

/**
 * Class Memcached
 * @package Comely\Cache\Store\PECL
 */
class Memcached extends AbstractCacheStore
{
    public const ENGINE = "MEMCACHED";

    /** @var null|\Memcached */
    private $memcached;

    /**
     * Memcached constructor.
     * @param CacheServer $server
     * @throws ConnectionException
     */
    public function __construct(CacheServer $server)
    {
        parent::__construct($server);
        $this->connect();
    }

    /**
     * @throws ConnectionException
     */
    public function connect(): void
    {
        if (!extension_loaded("memcached")) {
            throw new ConnectionException('PECL extension "memcached" not installed');
        }

        $this->memcached = new \Memcached();
        // \Memcached::OPT_BINARY_PROTOCOL is necessary for increment/decrement methods to work as expected
        $this->memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
        if (!$this->memcached->addServer($this->server->hostname, $this->server->port)) {
            throw new ConnectionException(
                sprintf('Failed to add %s:%d Memcached server', $this->server->hostname, $this->server->port)
            );
        }

        // Connected?
        if (!$this->isConnected()) {
            throw new ConnectionException(
                sprintf('Failed to connect with Memcached on %s:%d', $this->server->hostname, $this->server->port)
            );
        }
    }

    /**
     * @return void
     */
    public function disconnect(): void
    {
        if (!$this->memcached) {
            return;
        }

        $this->memcached->quit();
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        if (!$this->memcached) {
            return false;
        }

        $stats = $this->memcached->getStats();
        $server = sprintf('%s:%d', $this->server->hostname, $this->server->port);
        $pid = intval($stats[$server]["pid"] ?? 0);
        return $pid ? true : false;
    }

    /**
     * @return bool
     * @throws ConnectionException
     */
    public function ping(): bool
    {
        $connection = $this->isConnected(); // PID check is apparently sufficient
        if (!$connection) {
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
        $this->checkConnection();
        $this->memcached->set($key, $value, $ttl ?? 0);
        if ($this->memcached->getResultCode() !== \Memcached::RES_SUCCESS) {
            throw new CacheOpException('Failed to store data/object on Memcached server');
        }

        return true;
    }

    /**
     * @param string $key
     * @return mixed
     * @throws CacheOpException
     * @throws ConnectionException
     */
    public function get(string $key)
    {
        $this->checkConnection();
        $stored = $this->memcached->get($key);
        if (!$stored) {
            throw new CacheOpException('Failed to retrieve data/object from Memcached server');
        }

        return $stored;
    }

    /**
     * @param string $key
     * @return bool
     * @throws ConnectionException
     */
    public function has(string $key): bool
    {
        $this->checkConnection();
        $this->memcached->get($key);
        return $this->memcached->getResultCode() === \Memcached::RES_NOTFOUND ? false : true;
    }

    /**
     * @param string $key
     * @return bool
     * @throws ConnectionException
     */
    public function delete(string $key): bool
    {
        $this->checkConnection();
        return $this->memcached->delete($key);
    }

    /**
     * @return bool
     * @throws ConnectionException
     */
    public function flush(): bool
    {
        $this->checkConnection();
        return $this->memcached->flush();
    }

    /**
     * @throws ConnectionException
     */
    private function checkConnection()
    {
        if (!$this->memcached) {
            throw new ConnectionException('Not connected to any server');
        }
    }
}