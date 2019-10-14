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

namespace Comely\Cache;

use Comely\Cache\Exception\CachedItemException;
use Comely\Cache\Exception\CachedItemExpiredException;
use Comely\Cache\Exception\CacheOpException;
use Comely\Cache\Exception\ConnectionException;
use Comely\Cache\Store\AbstractCacheStore;
use Comely\Cache\Store\CacheStoreInterface;
use Comely\Cache\Store\PECL\Memcached;
use Comely\Cache\Store\Redis;

/**
 * Class Cache
 * @package Comely\Cache
 */
class Cache implements CacheStoreInterface
{
    /** string Version (Major.Minor.Release-Suffix) */
    public const VERSION = "1.0.24";
    /** int Version (Major * 10000 + Minor * 100 + Release) */
    public const VERSION_ID = 10024;

    public const SERIALIZED_PREFIX = "~comelyCachedItem";
    public const PLAIN_STRING_MAX_LEN = 64;

    public const REDIS = "redis";
    public const MEMCACHED = "memcached";
    public const STORES = [
        "redis",
        "memcached"
    ];

    /** @var null|CacheStoreInterface */
    private $store;
    /** @var Events */
    private $events;
    /** @var array */
    private $servers;

    /**
     * Cache constructor.
     */
    public function __construct()
    {
        $this->events = new Events();
        $this->servers = new Servers($this);
    }

    /**
     * @return Events
     */
    public function events(): Events
    {
        return $this->events;
    }

    /**
     * @return Servers
     */
    public function servers(): Servers
    {
        return $this->servers;
    }

    /**
     * @throws ConnectionException
     */
    public function connect(): void
    {
        if (!$this->servers->count()) {
            throw new ConnectionException('No servers in connection pool');
        }

        foreach ($this->servers as $server) {
            try {
                switch ($server->type) {
                    case self::REDIS:
                        $store = new Redis($server);
                        break;
                    case self::MEMCACHED:
                        $store = new Memcached($server);
                        break;
                }
            } catch (ConnectionException $e) {
                trigger_error($e->getMessage());
            }
        }

        if (!isset($store) || !$store instanceof AbstractCacheStore) {
            throw new ConnectionException('Failed to connect to cache server(s)');
        }

        $this->store = $store;
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        if ($this->store) {
            $connected = $this->store->isConnected();
            if (!$connected) {
                $this->store = null;
            }

            return $connected;
        }

        return false;
    }

    /**
     * @param bool $reconnect
     * @return bool
     * @throws ConnectionException
     */
    public function ping(bool $reconnect = false): bool
    {
        if ($this->store) {
            $ping = $this->store->ping();
            if (!$ping) {
                $this->store = null;
                if ($reconnect) {
                    $this->connect();
                }
            }

            return $ping;
        }

        return false;
    }

    /**
     * @return void
     */
    public function disconnect(): void
    {
        if ($this->store) {
            $this->store->disconnect();
        }

        $this->store = null;
    }

    /**
     * @param string $key
     * @param $value
     * @param int|null $ttl
     * @return bool
     * @throws CacheOpException
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $this->checkStoreInstance();
        $this->checkValidKey($key);

        if (is_string($value) && strlen($value) <= self::PLAIN_STRING_MAX_LEN) {
            if ($this->store->set($key, $value, $ttl)) {
                $this->events->onStored()->trigger([$key, "string"]);
                return true;
            }

            return false;
        }

        $serialized = serialize(new CachedItem($key, $value, $ttl));
        $padding = self::PLAIN_STRING_MAX_LEN - strlen($serialized);
        if ($padding > 0) {
            $serialized .= str_repeat("\0", $padding);
        }

        $set = $this->store->set($key, self::SERIALIZED_PREFIX . base64_encode($serialized), $ttl);
        if ($set) {
            $this->events->onStored()->trigger([$key, gettype($value)]);
            return true;
        }

        return $set;
    }

    /**
     * @param string $key
     * @param bool $returnCachedItemObj
     * @return bool|CachedItem|float|int|mixed|string|null
     * @throws CacheOpException
     * @throws CachedItemException
     * @throws CachedItemExpiredException
     */
    public function get(string $key, bool $returnCachedItemObj = false)
    {
        $this->checkStoreInstance();
        $this->checkValidKey($key);

        $stored = $this->store->get($key);
        if (!is_string($stored)) {
            return $stored;
        }

        if (preg_match('/^[0-9]+$/', $stored)) {
            return intval($stored);
        }

        $stored = trim($stored);
        if (strlen($stored) >= self::PLAIN_STRING_MAX_LEN) {
            $prefixLen = strlen(self::SERIALIZED_PREFIX);
            if (substr($stored, 0, $prefixLen) === self::SERIALIZED_PREFIX) {
                $cachedItem = unserialize(base64_decode(substr($stored, $prefixLen)));
                if (!$cachedItem instanceof CachedItem) {
                    throw new CachedItemException('Cannot retrieve serialized CachedItem object');
                }

                try {
                    return $returnCachedItemObj ? $cachedItem : $cachedItem->get();
                } catch (CachedItemExpiredException $e) {
                    try {
                        $this->delete($cachedItem->key);
                    } catch (CacheOpException $e) {
                    }

                    throw $e;
                }
            }
        }

        return $stored;
    }

    /**
     * @param string $key
     * @return bool
     * @throws CacheOpException
     */
    public function delete(string $key): bool
    {
        $this->checkStoreInstance();
        $this->checkValidKey($key);

        if ($this->store->delete($key)) {
            $this->events->onDelete()->trigger([$key]);
            return true;
        }

        return false;
    }

    /**
     * @return bool
     * @throws CacheOpException
     */
    public function flush(): bool
    {
        $this->checkStoreInstance();
        if ($this->store->flush()) {
            $this->events->onDelete()->trigger();
            return true;
        }

        return false;
    }

    /**
     * @param string $key
     * @return bool
     * @throws CacheOpException
     */
    public function has(string $key): bool
    {
        $this->checkStoreInstance();
        $this->checkValidKey($key);

        return $this->store->has($key);
    }

    /**
     * @throws CacheOpException
     */
    private function checkStoreInstance(): void
    {
        if (!$this->store) {
            throw new CacheOpException('Not connected to any cache server');
        }
    }

    /**
     * @param string $key
     * @throws CacheOpException
     */
    public function checkValidKey(string $key): void
    {
        if (!preg_match('/^[\w\-\.\@\:\#]+$/', $key)) {
            throw new CacheOpException('Invalid cache item key');
        }
    }
}