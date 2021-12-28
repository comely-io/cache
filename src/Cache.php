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

namespace Comely\Cache;

use Comely\Cache\Exception\CachedItemException;
use Comely\Cache\Exception\CacheException;
use Comely\Cache\Exception\RedisConnectionException;
use Comely\Cache\Pool\ServersPool;
use Comely\Cache\Redis\RedisClient;

/**
 * Class Cache
 * @package Comely\Cache
 */
class Cache
{
    /** string Version (Major.Minor.Release-Suffix) */
    public const VERSION = "2.0.2";
    /** int Version (Major * 10000 + Minor * 100 + Release) */
    public const VERSION_ID = 20002;

    /** @var ServersPool */
    private ServersPool $pool;
    /** @var RedisClient|null */
    private ?RedisClient $connected = null;
    /** @var array */
    private array $options = [
        "nullIfExpired" => true,
        "deleteIfExpired" => true,
    ];

    /**
     * Cache constructor.
     */
    public function __construct()
    {
        $this->pool = new ServersPool();
    }

    /**
     * @param bool|null $nullIfExpired
     * @param bool|null $deleteIfExpired
     * @return $this
     */
    public function options(?bool $nullIfExpired = null, ?bool $deleteIfExpired = null): self
    {
        if (is_bool($nullIfExpired)) {
            $this->options["nullIfExpired"] = $nullIfExpired;
        }

        if (is_bool($deleteIfExpired)) {
            $this->options["deleteIfExpired"] = $deleteIfExpired;
        }

        return $this;
    }

    /**
     * @return ServersPool
     */
    public function pool(): ServersPool
    {
        return $this->pool;
    }

    /**
     * @param array|null $errors
     * @throws CacheException
     */
    public function connect(array &$errors = null): void
    {
        if ($this->isConnected()) {
            return;
        }

        $errors = [];
        if (!$this->pool->count()) {
            throw new CacheException('There are no servers configured');
        }

        /** @var RedisClient $server */
        foreach ($this->pool->servers() as $server) {
            try {
                $server->connect();
                $this->connected = $server;
            } catch (RedisConnectionException $e) {
                /** @noinspection PhpArrayWriteIsNotUsedInspection */
                $errors[] = $e;
            }
        }

        if (!$this->connected) {
            throw new CacheException('Could not connect to any server');
        }
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        $connected = $this->connected?->isConnected() ?? false;
        if (!$connected) {
            $this->connected = null;
        }

        return $connected;
    }

    /**
     * @param RedisClient|null $server
     * @return RedisClient
     * @throws CacheException
     */
    private function getConnectedServer(?RedisClient $server = null): RedisClient
    {
        if ($server) {
            return $server;
        }

        if (!$this->connected) {
            $this->connect($errors);
            if (!$this->connected) {
                /** @var RedisConnectionException $error */
                $error = $errors[0] ?? null;
                if ($error) {
                    throw $error;
                }
            }
        }

        return $this->connected;
    }

    /**
     * @return void
     */
    public function disconnect(): void
    {
        $this->connected?->disconnect();
        $this->connected = null;
    }

    /**
     * @param RedisClient|null $server
     * @return bool
     * @throws CacheException
     */
    public function ping(?RedisClient $server = null): bool
    {
        return $this->getConnectedServer($server)->ping();
    }

    /**
     * @param string $key
     * @param int|string|array|object|bool|null $value
     * @param int|null $ttl
     * @param RedisClient|null $server
     * @return bool
     * @throws CacheException
     */
    public function set(string $key, int|string|null|array|object|bool $value, ?int $ttl = null, ?RedisClient $server = null): bool
    {
        return $this->getConnectedServer($server)->set($key, CachedItem::Prepare($key, $value, $ttl), $ttl);
    }

    /**
     * @param string $key
     * @param RedisClient|null $server
     * @return int|string|array|object|bool|null
     * @throws CacheException
     */
    public function get(string $key, ?RedisClient $server = null): int|string|null|array|object|bool
    {
        $stored = $this->getConnectedServer($server)->get($key);
        if (!is_string($stored)) {
            return $stored;
        }

        $cachedItem = CachedItem::Decode($stored);
        if (!$cachedItem instanceof CachedItem) {
            return $cachedItem;
        }

        try {
            return $cachedItem->getStoredItem();
        } catch (CachedItemException $e) {
            // Handle expired item
            if ($e->getCode() === CachedItemException::IS_EXPIRED) {
                if ($this->options["deleteIfExpired"]) {
                    try {
                        $this->delete($key);
                    } catch (CacheException) {
                    }
                }

                if ($this->options["nullIfExpired"]) {
                    return null;
                }
            }

            throw $e;
        }
    }

    /**
     * @param string $key
     * @param RedisClient|null $server
     * @return bool
     * @throws CacheException
     */
    public function delete(string $key, ?RedisClient $server = null): bool
    {
        return $this->getConnectedServer($server)->delete($key);
    }

    /**
     * @param RedisClient|null $server
     * @return bool
     * @throws CacheException
     */
    public function flush(?RedisClient $server): bool
    {
        return $this->getConnectedServer($server)->flush();
    }

    /**
     * @param string $key
     * @param RedisClient|null $server
     * @return bool
     * @throws CacheException
     */
    public function has(string $key, ?RedisClient $server): bool
    {
        return $this->getConnectedServer($server)->has($key);
    }
}
