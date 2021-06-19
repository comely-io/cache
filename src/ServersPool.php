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
use Comely\Cache\Pool\BulkCacheOp;
use Comely\Cache\Redis\RedisClient;

/**
 * Class Servers
 * @package Comely\Cache
 */
class ServersPool
{
    /** @var array */
    private array $servers = [];
    /** @var int */
    private int $count = 0;

    /**
     * @param string $tag
     * @param string $host
     * @param int $port
     * @param int $timeOut
     * @return $this
     */
    public function addRedisServer(string $tag, string $host, int $port = 6379, int $timeOut = 1): self
    {
        $this->servers[$tag] = new RedisClient($host, $port, $timeOut);
        $this->count++;
        return $this;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * @param string $tag
     * @return RedisClient|null
     */
    public function server(string $tag): ?RedisClient
    {
        return $this->servers[$tag] ?? null;
    }

    /**
     * @return array
     */
    public function servers(): array
    {
        return $this->servers;
    }

    /**
     * @param \Closure $method
     * @return BulkCacheOp
     */
    private function bulkCacheOp(\Closure $method): BulkCacheOp
    {
        $bulkCacheOp = new BulkCacheOp();

        /** @var RedisClient $server */
        foreach ($this->servers as $server) {
            $bulkCacheOp->total++;

            try {
                $success = call_user_func_array($method, [$server]);
                if ($success) {
                    $bulkCacheOp->success++;
                    continue;
                }

                $bulkCacheOp->fails++;
            } catch (\Exception $e) {
                $bulkCacheOp->errors[] = $e;
            }
        }

        return $bulkCacheOp;
    }

    /**
     * Stores a value on all servers in pool
     * @param string $key
     * @param object|int|bool|array|string|null $value
     * @param int|null $ttl
     * @return BulkCacheOp
     */
    public function set(string $key, object|int|bool|array|string|null $value, ?int $ttl = null): BulkCacheOp
    {
        return $this->bulkCacheOp(function (RedisClient $server) use ($key, $value, $ttl) {
            return $server->set($key, $value, $ttl);
        });
    }

    /**
     * @param string $key
     * @return int|string|array|object|bool|null
     */
    public function get(string $key): int|string|null|array|object|bool
    {
        /** @var RedisClient $server */
        foreach ($this->servers() as $server) {
            try {
                $stored = $server->get($key);
                if (!is_string($stored)) {
                    return $stored;
                }

                $cachedItem = CachedItem::Decode($stored);
                if (!$cachedItem instanceof CachedItem) {
                    return $cachedItem;
                }

                try {
                    return $cachedItem->getStoredItem();
                } catch (CachedItemException) {
                }
            } catch (CacheException) {
            }
        }

        return null;
    }

    /**
     * @param string $key
     * @return array
     */
    public function has(string $key): array
    {
        $servers = [];
        /** @var RedisClient $server */
        foreach ($this->servers as $server) {
            try {
                if ($server->has($key)) {
                    $servers[] = $server;
                }
            } catch (CacheException) {
            }
        }

        return $servers;
    }

    /**
     * @param string $key
     * @return BulkCacheOp
     */
    public function delete(string $key): BulkCacheOp
    {
        return $this->bulkCacheOp(function (RedisClient $server) use ($key) {
            return $server->delete($key);
        });
    }

    /**
     * @return BulkCacheOp
     */
    public function flush(): BulkCacheOp
    {
        return $this->bulkCacheOp(function (RedisClient $server) {
            return $server->flush();
        });
    }

    /**
     * @return BulkCacheOp
     */
    public function ping(): BulkCacheOp
    {
        return $this->bulkCacheOp(function (RedisClient $server) {
            return $server->ping();
        });
    }
}
